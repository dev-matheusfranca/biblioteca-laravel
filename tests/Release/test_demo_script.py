import contextlib
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest import mock


ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('demo_script', ROOT / 'scripts' / 'demo.py')
demo = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(demo)


class DemoScriptSafetyTest(unittest.TestCase):
    def test_remote_docker_host_is_rejected_before_any_docker_call(self):
        with mock.patch.object(demo.subprocess, 'run') as execute:
            with self.assertRaisesRegex(RuntimeError, 'DOCKER_HOST remoto'):
                demo.local_docker_command(['docker', 'compose', 'up'], {'DOCKER_HOST': 'tcp://127.0.0.1:9'})
        execute.assert_not_called()

    def test_remote_context_is_rejected_without_contacting_daemon(self):
        responses = [subprocess.CompletedProcess([], 0, b'remote\n'), subprocess.CompletedProcess([], 0, b'"ssh://example.test"')]
        with mock.patch.object(demo.subprocess, 'run', side_effect=responses) as execute:
            with self.assertRaisesRegex(RuntimeError, 'contexto remoto'):
                demo.local_docker_command(['docker', 'compose', 'stop'], {})
        self.assertEqual(execute.call_count, 2)
        self.assertTrue(all(call.args[0][1] == 'context' for call in execute.call_args_list))

    def test_local_context_is_pinned_for_the_actual_command(self):
        responses = [subprocess.CompletedProcess([], 0, b'desktop-linux\n'), subprocess.CompletedProcess([], 0, b'"npipe:////./pipe/dockerDesktopLinuxEngine"')]
        with mock.patch.object(demo.subprocess, 'run', side_effect=responses):
            args, env = demo.local_docker_command(['docker', 'compose', 'up'], {})
        self.assertEqual(args, ['docker', '--context', 'desktop-linux', 'compose', 'up'])
        self.assertEqual(env['DOCKER_CONTEXT'], 'desktop-linux')
        self.assertFalse(demo.is_local_endpoint('npipe:////remote/pipe/docker'))
        self.assertTrue(demo.is_local_endpoint('unix:///var/run/docker.sock'))

    def test_env_file_rejects_project_and_host_overrides(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            with mock.patch.object(demo, 'LOCAL', root), mock.patch.object(demo, 'ENV_FILE', root / '.env.demo'):
                demo.init()
                with demo.ENV_FILE.open('a', encoding='utf-8') as target:
                    target.write('COMPOSE_PROJECT_NAME=another-project\nDOCKER_HOST=tcp://127.0.0.1:9\n')
                with self.assertRaisesRegex(RuntimeError, 'oito variáveis'):
                    demo.init()
        self.assertIn('biblioteca-demo', demo.COMPOSE)
        self.assertEqual(demo.COMPOSE[2:4], ['-p', 'biblioteca-demo'])

    def test_fingerprint_includes_dockerignore(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for name in ['artisan', 'composer.json', 'composer.lock', 'compose.demo.yaml', '.dockerignore']:
                (root / name).write_text(name, encoding='utf-8')
            with mock.patch.object(demo, 'ROOT', root):
                before = demo.fingerprint()
                (root / '.dockerignore').write_text('changed', encoding='utf-8')
                after = demo.fingerprint()
        self.assertNotEqual(before, after)

    def test_sensitive_failure_never_persists_process_output_or_arguments(self):
        sentinel = 'SECRET-SENTINEL'
        completed = subprocess.CompletedProcess(
            ['unsafe', sentinel],
            17,
            stdout=sentinel.encode(),
            stderr=('stderr-' + sentinel).encode(),
        )
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            with (
                mock.patch.object(demo, 'ROOT', root),
                mock.patch.object(demo, 'environment', return_value={}),
                mock.patch.object(demo.subprocess, 'run', return_value=completed),
                self.assertRaises(RuntimeError),
            ):
                demo.run(['unsafe', sentinel], sensitive_output=True)
            logs = list((root / 'output' / 'testing').glob('demo-error-*.log'))
            self.assertEqual(len(logs), 1)
            evidence = logs[0].read_text(encoding='utf-8')
        self.assertNotIn(sentinel, evidence)
        self.assertIn('Código de saída: 17', evidence)
        self.assertIn('foram suprimidos', evidence)

    def test_seed_secrets_are_forwarded_by_name_only(self):
        with mock.patch.object(demo, 'output', return_value='') as execute:
            demo.app_command(
                'db:seed',
                '--class=DemoSeeder',
                release='release-a',
                environment_keys=demo.DEMO_SEED_SECRET_KEYS,
                sensitive_output=True,
            )
        args = execute.call_args.args[0]
        rendered = ' '.join(args)
        for key in demo.DEMO_SEED_SECRET_KEYS:
            self.assertIn(key, args)
            self.assertNotIn(key + '=', rendered)
        self.assertEqual(args.count('-e'), 3)
        self.assertTrue(execute.call_args.kwargs['sensitive_output'])

    def test_backup_helper_refuses_a_running_writer(self):
        with (
            mock.patch.object(demo, 'running_runtime_services', return_value=['container-id']),
            mock.patch.object(demo, 'canary') as canary,
            self.assertRaisesRegex(RuntimeError, 'app, worker e scheduler parados'),
        ):
            demo.backup_quiesced('release-a')
        canary.assert_not_called()

    def test_public_backup_restarts_runtime_even_when_dump_fails(self):
        current = {
            'current': 'release-a',
            'current_image': 'sha256:image-a',
            'dependencies_signature': 'deps-a',
            'seeded': True,
        }
        with (
            mock.patch.object(demo, 'state', return_value=current),
            mock.patch.object(demo, 'assert_running_identity'),
            mock.patch.object(demo, 'assert_dependencies_signature'),
            mock.patch.object(demo, 'quiesce_runtime') as quiesce,
            mock.patch.object(demo, 'backup_quiesced', side_effect=RuntimeError('dump failed')),
            mock.patch.object(demo, 'start_runtime') as restart,
            mock.patch.object(demo, 'http_smoke') as smoke,
            self.assertRaisesRegex(RuntimeError, 'dump failed'),
        ):
            demo.backup()
        quiesce.assert_called_once_with('release-a')
        restart.assert_called_once_with('release-a')
        smoke.assert_not_called()

    def test_public_backup_restarts_runtime_after_partial_quiesce_failure(self):
        current = {
            'current': 'release-a',
            'current_image': 'sha256:image-a',
            'dependencies_signature': 'deps-a',
            'seeded': True,
        }
        with (
            mock.patch.object(demo, 'state', return_value=current),
            mock.patch.object(demo, 'assert_running_identity'),
            mock.patch.object(demo, 'assert_dependencies_signature'),
            mock.patch.object(demo, 'quiesce_runtime', side_effect=RuntimeError('partial stop')),
            mock.patch.object(demo, 'backup_quiesced') as create_backup,
            mock.patch.object(demo, 'start_runtime') as restart,
            self.assertRaisesRegex(RuntimeError, 'partial stop'),
        ):
            demo.backup()
        create_backup.assert_not_called()
        restart.assert_called_once_with('release-a')

    def test_rollback_checks_dependency_signature_before_quiescing_or_journaling(self):
        current = self.rollback_state()
        with tempfile.TemporaryDirectory() as directory:
            with (
                mock.patch.object(demo, 'ATTEMPT_FILE', Path(directory) / 'attempt.json'),
                mock.patch.object(demo, 'release_lock', return_value=contextlib.nullcontext()),
                mock.patch.object(demo, 'state', return_value=current),
                mock.patch.object(demo, 'image_identity', return_value='sha256:image-old'),
                mock.patch.object(demo, 'assert_running_identity'),
                mock.patch.object(demo, 'assert_dependencies_signature', side_effect=[None, RuntimeError('deps changed')]),
                mock.patch.object(demo, 'save_attempt') as save_attempt,
                mock.patch.object(demo, 'quiesce_runtime') as quiesce,
                self.assertRaisesRegex(RuntimeError, 'deps changed'),
            ):
                demo.rollback()
        save_attempt.assert_not_called()
        quiesce.assert_not_called()

    def test_rollback_failure_uses_journal_and_attempts_last_known_good_recovery(self):
        current = self.rollback_state()
        with tempfile.TemporaryDirectory() as directory:
            with (
                mock.patch.object(demo, 'ATTEMPT_FILE', Path(directory) / 'attempt.json'),
                mock.patch.object(demo, 'release_lock', return_value=contextlib.nullcontext()),
                mock.patch.object(demo, 'state', return_value=current),
                mock.patch.object(demo, 'image_identity', return_value='sha256:image-old'),
                mock.patch.object(demo, 'assert_running_identity'),
                mock.patch.object(demo, 'assert_dependencies_signature'),
                mock.patch.object(demo, 'save_attempt') as save_attempt,
                mock.patch.object(demo, 'quiesce_runtime'),
                mock.patch.object(demo, 'backup_quiesced', side_effect=RuntimeError('dump failed')),
                mock.patch.object(demo, 'recover_unlocked') as recover,
                self.assertRaisesRegex(RuntimeError, 'dump failed'),
            ):
                demo.rollback()
        first_journal = save_attempt.call_args_list[0].args[0]
        self.assertEqual(first_journal['operation'], 'rollback')
        self.assertEqual(first_journal['last_known_good'], current)
        self.assertEqual(first_journal['candidate'], 'release-old')
        recover.assert_called_once_with()

    def test_resume_init_reuses_exact_journaled_artifact_and_dependencies(self):
        attempt = {
            'operation': 'deploy',
            'candidate': 'release-a',
            'candidate_image': 'sha256:image-a',
            'last_known_good': {},
            'dependencies_signature': 'deps-a',
            'seed': True,
            'stage': 'initialization_incomplete',
        }
        with tempfile.TemporaryDirectory() as directory:
            attempt_file = Path(directory) / 'attempt.json'
            attempt_file.write_text(json.dumps(attempt), encoding='utf-8')
            with (
                mock.patch.object(demo, 'ATTEMPT_FILE', attempt_file),
                mock.patch.object(demo, 'release_lock', return_value=contextlib.nullcontext()),
                mock.patch.object(demo, 'image_identity', return_value='sha256:image-a'),
                mock.patch.object(demo, 'assert_dependencies_signature') as dependencies,
                mock.patch.object(demo, 'execute_deploy_attempt') as execute,
            ):
                demo.resume_initialization()
        dependencies.assert_called_once_with('release-a', 'deps-a')
        execute.assert_called_once_with(attempt)

    @staticmethod
    def rollback_state():
        return {
            'current': 'release-current',
            'current_image': 'sha256:image-current',
            'previous': 'release-old',
            'previous_image': 'sha256:image-old',
            'previous_schema': 'schema-a',
            'dependencies_signature': 'deps-a',
            'seeded': True,
            'integrity': {'schema_signature': 'schema-a', 'counts': {'users': 5}},
        }


if __name__ == '__main__':
    unittest.main()
