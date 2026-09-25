"""Local Docker release, backup and recovery. Never targets development or external databases."""
import argparse
import base64
from contextlib import contextmanager
from datetime import datetime, timezone
import hashlib
from html.parser import HTMLParser
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import time
import urllib.parse
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]
LOCAL = ROOT / '.demo'
ENV_FILE = ROOT / '.env.demo'
STATE_FILE = LOCAL / 'state.json'
ATTEMPT_FILE = LOCAL / 'attempt.json'
COMPOSE = ['docker', 'compose', '-p', 'biblioteca-demo', '--env-file', '.env.demo', '-f', 'compose.demo.yaml']
RUNTIME_SERVICES = ['app', 'worker', 'scheduler']
DEMO_SEED_SECRET_KEYS = ['DEMO_ADMIN_PASSWORD', 'DEMO_STAFF_PASSWORD', 'DEMO_READER_PASSWORD']

def timestamp():
    return datetime.now(timezone.utc).strftime('%Y%m%d%H%M%S%f')

def init():
    LOCAL.mkdir(exist_ok=True)
    values = {'DEMO_APP_PORT': '8089', 'DEMO_MAIL_PORT': '8029',
              'DEMO_APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode()}
    for key in ['DB_PASSWORD', 'DB_ROOT_PASSWORD', 'ADMIN_PASSWORD', 'STAFF_PASSWORD', 'READER_PASSWORD']:
        values['DEMO_' + key] = secrets.token_hex(24)
    try:
        with ENV_FILE.open('x', encoding='utf-8') as target:
            target.write(''.join(f'{key}={value}\n' for key, value in values.items()))
        ENV_FILE.chmod(0o600)
    except FileExistsError:
        pass
    configured = dict(line.split('=', 1) for line in ENV_FILE.read_text(encoding='utf-8').splitlines() if line and not line.startswith('#'))
    if configured.keys() != values.keys():
        raise RuntimeError('.env.demo deve conter somente as oito variáveis DEMO_* geradas pelo inicializador.')
    return configured

def state():
    return json.loads(STATE_FILE.read_text(encoding='utf-8')) if STATE_FILE.exists() else {}

def save_state(value):
    temporary = LOCAL / 'state.tmp'
    temporary.write_text(json.dumps(value, indent=2) + '\n', encoding='utf-8')
    temporary.replace(STATE_FILE)

def save_attempt(value):
    temporary = LOCAL / 'attempt.tmp'
    temporary.write_text(json.dumps(value, indent=2) + '\n', encoding='utf-8')
    temporary.replace(ATTEMPT_FILE)

def environment(release=None):
    return {**os.environ, **init(), 'DEMO_RELEASE': release or state().get('current', 'uninitialized')}

def is_local_endpoint(endpoint):
    return endpoint.startswith('unix:///') or bool(re.fullmatch(r'npipe:/{4}\./pipe/[A-Za-z0-9_.-]+', endpoint))

def local_docker_command(args, env):
    # Inspect metadata before contacting the daemon, then pin the checked context.
    if env.get('DOCKER_HOST') and not is_local_endpoint(env['DOCKER_HOST']):
        raise RuntimeError('Demonstração restrita a Docker local: DOCKER_HOST remoto foi recusado.')
    shown = subprocess.run(['docker', 'context', 'show'], env=env, capture_output=True)
    if shown.returncode:
        raise RuntimeError('Não foi possível identificar o contexto Docker local.')
    context = shown.stdout.decode('utf-8').strip()
    inspected = subprocess.run(['docker', 'context', 'inspect', context, '--format', '{{json .Endpoints.docker.Host}}'], env=env, capture_output=True)
    if inspected.returncode or not is_local_endpoint(json.loads(inspected.stdout)):
        raise RuntimeError('Demonstração restrita a socket Docker local; contexto remoto foi recusado.')
    pinned_env = {**env, 'DOCKER_CONTEXT': context}
    pinned_env.pop('DOCKER_HOST', None)
    return ['docker', '--context', context, *args[1:]], pinned_env

def run(args, release=None, input_data=None, allow_failure=False, sensitive_output=False):
    env = environment(release)
    if args[0] == 'docker':
        args, env = local_docker_command(args, env)
    result = subprocess.run(args, cwd=ROOT, env=env, input=input_data, capture_output=True)
    if result.returncode and not allow_failure:
        log = ROOT / 'output/testing' / ('demo-error-' + timestamp() + '.log')
        log.parent.mkdir(parents=True, exist_ok=True)
        if sensitive_output:
            log.write_text(
                'Comando sensível falhou; stdout, stderr, argumentos e ambiente foram suprimidos. '
                f'Código de saída: {result.returncode}.\n',
                encoding='utf-8',
            )
        else:
            log.write_bytes(result.stdout + result.stderr)
        raise RuntimeError('Comando falhou; evidência local em ' + str(log.relative_to(ROOT)))
    return result

def output(args, **kwargs):
    return run(args, **kwargs).stdout.decode('utf-8', errors='replace').strip()

@contextmanager
def release_lock():
    init()
    lock = LOCAL / 'release.lock'
    try:
        handle = lock.open('x', encoding='utf-8')
    except FileExistsError:
        raise RuntimeError('Há uma operação de release em curso ou um lock residual; confira .demo/release.lock.')
    try:
        with handle:
            handle.write(str(os.getpid()))
        yield
    finally:
        lock.unlink(missing_ok=True)

def fingerprint():
    digest = hashlib.sha256()
    paths = []
    for name in ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'docker']:
        paths.extend(path for path in (ROOT / name).rglob('*') if path.is_file())
    paths.extend(ROOT / name for name in ['artisan', 'composer.json', 'composer.lock', 'compose.demo.yaml', '.dockerignore'])
    for path in sorted(paths):
        relative = path.relative_to(ROOT).as_posix()
        if relative.startswith('bootstrap/cache/') or '.sqlite' in path.name or path.suffix == '.log' or path.name.startswith('.env'):
            continue
        digest.update(relative.encode() + b'\0' + path.read_bytes() + b'\0')
    return digest.hexdigest()[:20]

def app_command(*arguments, release=None, database=None, environment_keys=(), sensitive_output=False):
    overrides = []
    if database:
        if not re.fullmatch(r'biblioteca_demo_restore_[0-9]+', database):
            raise RuntimeError('Banco de restauração inválido.')
        overrides = ['-e', 'DB_DATABASE=' + database, '-e', 'CACHE_PREFIX=' + database + ':', '-v', database + '_storage:/var/www/html/storage']
    for key in environment_keys:
        if key not in DEMO_SEED_SECRET_KEYS:
            raise RuntimeError('Variável efêmera não autorizada para o comando da demonstração.')
        overrides.extend(['-e', key])
    return output(
        COMPOSE + ['run', '--rm', '--no-deps'] + overrides + ['app', 'php', 'artisan', *arguments],
        release=release,
        sensitive_output=sensitive_output,
    )

def canary(release=None, database=None):
    return json.loads(app_command('demo:canary', release=release, database=database))

def wait_http(port):
    for _ in range(40):
        try:
            with urllib.request.urlopen(f'http://127.0.0.1:{port}/up', timeout=2) as response:
                if response.status == 200:
                    return
        except Exception:
            time.sleep(.5)
    raise RuntimeError('O HTTP local não ficou disponível.')

class CsrfParser(HTMLParser):
    token = None
    def handle_starttag(self, tag, attrs):
        fields = dict(attrs)
        if tag == 'input' and fields.get('name') == '_token':
            self.token = fields.get('value')

def http_smoke(port, authenticated=False):
    base = f'http://127.0.0.1:{port}'
    with urllib.request.urlopen(base + '/catalogo', timeout=10) as response:
        if response.status != 200 or 'Catálogo público' not in response.read().decode('utf-8'):
            raise RuntimeError('Canário do catálogo falhou.')
    if authenticated:
        browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        with browser.open(base + '/login', timeout=10) as response:
            parser = CsrfParser()
            parser.feed(response.read().decode('utf-8'))
        if not parser.token:
            raise RuntimeError('Formulário de autenticação inválido.')
        data = urllib.parse.urlencode({'_token': parser.token, 'email': 'admin@biblioteca.example.test', 'password': init()['DEMO_ADMIN_PASSWORD']}).encode()
        with browser.open(base + '/login', data=data, timeout=10) as response:
            response.read()
        with browser.open(base + '/equipe', timeout=10) as response:
            if response.status != 200 or '/login' in response.url or 'Equipe' not in response.read().decode('utf-8'):
                raise RuntimeError('Canário autenticado falhou.')

def image_identity(release):
    if not re.fullmatch(r'[a-z0-9][a-z0-9_.-]{0,63}', release):
        raise RuntimeError('Identificador de imagem inválido.')
    return output(['docker', 'image', 'inspect', 'biblioteca-demo-app:' + release, '--format', '{{.Id}}'])

def dependencies_signature(release):
    resolved = json.loads(output(COMPOSE + ['config', '--format', 'json'], release=release, sensitive_output=True))
    dependencies = {name: resolved['services'][name] for name in ['mysql', 'redis', 'redis-cache', 'mailpit']}
    return hashlib.sha256(json.dumps(dependencies, sort_keys=True).encode()).hexdigest()

def assert_dependencies_signature(release, expected):
    if not expected or dependencies_signature(release) != expected:
        raise RuntimeError('A assinatura das dependências diverge do estado validado; nenhuma troca de runtime foi iniciada.')

def running_runtime_services(release):
    running = output(
        COMPOSE + ['ps', '-q', '--status', 'running', *RUNTIME_SERVICES],
        release=release,
    )
    return [line for line in running.splitlines() if line]

def assert_runtime_quiesced(release):
    if running_runtime_services(release):
        raise RuntimeError('O backup exige app, worker e scheduler parados para produzir um snapshot consistente.')

def quiesce_runtime(release):
    run(COMPOSE + ['stop', *RUNTIME_SERVICES], release=release)
    assert_runtime_quiesced(release)

def start_runtime(release):
    run(
        COMPOSE + ['up', '-d', '--wait', '--wait-timeout', '150', *RUNTIME_SERVICES],
        release=release,
    )

def assert_running_identity(release, expected):
    identifier = output(COMPOSE + ['ps', '-aq', 'app'], release=release)
    if not identifier or output(['docker', 'inspect', identifier, '--format', '{{.Image}}']) != expected or image_identity(release) != expected:
        raise RuntimeError('Container, estado e tag de release divergem. Use o journal de recuperação; não faça backup com identidade presumida.')

def backup_quiesced(release):
    assert_runtime_quiesced(release)
    counts = canary(release)['counts']
    dump = run(
        COMPOSE + ['exec', '-T', 'mysql', 'sh', '-c', 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump -h 127.0.0.1 -u biblioteca_demo --single-transaction --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF biblioteca_demo'],
        release=release,
        sensitive_output=True,
    ).stdout
    destination = ROOT / '__FILES/bd'
    destination.mkdir(parents=True, exist_ok=True)
    archive = destination / ('biblioteca_demo_' + timestamp() + '.sql.zip')
    manifest = {'database': 'biblioteca_demo', 'environment': 'docker-demo-local', 'created_at': datetime.now(timezone.utc).isoformat(), 'sha256': hashlib.sha256(dump).hexdigest(), 'release': release, 'image_id': image_identity(release), 'counts_before_dump': counts}
    with zipfile.ZipFile(archive, 'x', compression=zipfile.ZIP_DEFLATED) as target:
        target.writestr('biblioteca_demo.sql', dump)
        target.writestr('manifest.json', json.dumps(manifest, indent=2))
    with zipfile.ZipFile(archive) as target:
        if target.testzip() is not None:
            raise RuntimeError('Arquivo de backup inválido.')
    return archive

def backup(release=None):
    release = release or state().get('current')
    if not release:
        raise RuntimeError('Publique a demonstração antes de gerar backup.')
    current = state()
    if current.get('current') != release:
        raise RuntimeError('O backup exige a versão atual registrada.')
    assert_running_identity(release, current['current_image'])
    assert_dependencies_signature(release, current.get('dependencies_signature'))
    try:
        quiesce_runtime(release)
        archive = backup_quiesced(release)
    finally:
        start_runtime(release)
    assert_running_identity(release, current['current_image'])
    http_smoke(int(init()['DEMO_APP_PORT']), authenticated=current.get('seeded', False))
    print(json.dumps({'backup': str(archive), 'bytes': archive.stat().st_size, 'integrity': 'verified', 'writers_restarted': True}), flush=True)
    return archive

def execute_deploy_attempt(attempt):
    release = attempt['candidate']
    previous = attempt['last_known_good']
    dependencies = attempt['dependencies_signature']
    if image_identity(release) != attempt['candidate_image']:
        raise RuntimeError('O artefato candidato diverge do journal; a tentativa foi preservada.')
    assert_dependencies_signature(release, dependencies)
    print('Iniciando dependências isoladas.', flush=True)
    run(COMPOSE + ['up', '-d', '--wait', '--wait-timeout', '180', 'mysql', 'redis', 'redis-cache', 'mailpit'], release=release)
    # A canary reads the migration fingerprint before any DDL.
    if previous.get('current') and not attempt.get('candidate_schema'):
        attempt['candidate_schema'] = canary(release)['schema_signature']
    attempt['stage'] = 'migration_started'
    save_attempt(attempt)
    app_command('migrate', '--force', release=release)
    if attempt.get('seed'):
        app_command(
            'db:seed', '--class=DemoSeeder', '--force',
            release=release,
            environment_keys=DEMO_SEED_SECRET_KEYS,
            sensitive_output=True,
        )
        app_command('comunicacoes:preparar', release=release)
        app_command('outbox:publicar', release=release)
    attempt['stage'] = 'runtime_switch_started'
    save_attempt(attempt)
    print('Iniciando aplicação, worker e agendador.', flush=True)
    start_runtime(release)
    port = int(init()['DEMO_APP_PORT'])
    seeded = bool(attempt.get('seed') or previous.get('seeded', False))
    http_smoke(port, authenticated=seeded)
    integrity = canary(release)
    health = json.loads(output(COMPOSE + ['exec', '-T', 'app', 'php', 'artisan', 'operacoes:saude', '--json'], release=release))
    next_state = {'current': release, 'current_image': image_identity(release), 'previous': previous.get('current'), 'previous_image': previous.get('current_image'), 'previous_schema': previous.get('integrity', {}).get('schema_signature'), 'dependencies_signature': dependencies, 'seeded': seeded, 'updated_at': datetime.now(timezone.utc).isoformat(), 'integrity': integrity, 'health': health['status']}
    # Re-running the same release must retain the last distinct rollback target.
    if previous.get('current') == release:
        next_state['previous'] = previous.get('previous')
        next_state['previous_image'] = previous.get('previous_image')
        next_state['previous_schema'] = previous.get('previous_schema')
    save_state(next_state)
    ATTEMPT_FILE.unlink()
    print(json.dumps({'deployed': release, 'url': f'http://localhost:{port}', 'health': health['status'], **integrity}), flush=True)

def deploy(seed=False, existing_image=None):
    with release_lock():
        if ATTEMPT_FILE.exists():
            raise RuntimeError('Há uma tentativa não concluída. Execute recover antes de um novo deploy.')
        previous = state()
        release = existing_image or fingerprint()
        inspected = run(['docker', 'image', 'inspect', 'biblioteca-demo-app:' + release, '--format', '{{json .}}'], allow_failure=True)
        if inspected.returncode == 0:
            identity = json.loads(inspected.stdout)
            if identity['Config'].get('Labels', {}).get('org.opencontainers.image.revision') != release:
                raise RuntimeError('A tag existente não corresponde ao identificador de release esperado.')
            print('Reutilizando artefato imutável ' + release, flush=True)
        elif existing_image:
            raise RuntimeError('A imagem local indicada não existe.')
        else:
            print('Construindo imagem de demonstração ' + release, flush=True)
            run(['docker', 'build', '-f', 'docker/Dockerfile.production', '--build-arg', 'RELEASE_ID=' + release, '-t', 'biblioteca-demo-app:' + release, '.'], release=release)
        dependencies = dependencies_signature(release)
        if previous.get('current'):
            assert_running_identity(previous['current'], previous['current_image'])
            assert_dependencies_signature(previous['current'], previous.get('dependencies_signature'))
            if previous.get('dependencies_signature') != dependencies:
                raise RuntimeError('Configuração de dependências alterada. Prepare um upgrade e backup específico antes de tocar nos volumes persistentes.')
        attempt = {'operation': 'deploy', 'candidate': release, 'candidate_image': image_identity(release), 'last_known_good': previous,
                   'dependencies_signature': dependencies, 'seed': seed, 'stage': 'prepared'}
        save_attempt(attempt)
        try:
            if previous.get('current'):
                quiesce_runtime(previous['current'])
                archive = backup_quiesced(previous['current'])
                attempt['backup'] = str(archive.relative_to(ROOT))
                save_attempt(attempt)
            execute_deploy_attempt(attempt)
        except Exception:
            try:
                recover_unlocked()
            except Exception:
                print('Recuperação automática não concluída; journal preservado em .demo/attempt.json.', flush=True)
            raise

def recover_unlocked():
    if not ATTEMPT_FILE.exists():
        raise RuntimeError('Não há tentativa pendente para recuperar.')
    attempt = json.loads(ATTEMPT_FILE.read_text(encoding='utf-8'))
    good = attempt['last_known_good']
    if not good.get('current'):
        # First initialization has no previous version. Preserve the database for a corrected retry.
        run(COMPOSE + ['stop', *RUNTIME_SERVICES], release=attempt['candidate'], allow_failure=True)
        attempt['stage'] = 'initialization_incomplete'
        save_attempt(attempt)
        raise RuntimeError('Primeira inicialização incompleta; nenhum banco foi apagado. Corrija a causa e execute resume-init para retomar o mesmo artefato.')
    expected_schema = good.get('integrity', {}).get('schema_signature')
    # Recovery must not boot candidate code that already failed. The pre-DDL
    # signature captured in the journal is the only accepted compatibility gate.
    schema_safe = attempt['stage'] == 'prepared' or attempt.get('candidate_schema') == expected_schema
    signatures_match = attempt.get('dependencies_signature') == good.get('dependencies_signature')
    if not schema_safe or not signatures_match or image_identity(good['current']) != good['current_image']:
        raise RuntimeError('Recuperação automática bloqueada por divergência de schema, dependências ou artefato.')
    assert_dependencies_signature(good['current'], good.get('dependencies_signature'))
    assert_dependencies_signature(attempt['candidate'], attempt.get('dependencies_signature'))
    run(COMPOSE + ['stop', *RUNTIME_SERVICES], release=attempt['candidate'], allow_failure=True)
    start_runtime(good['current'])
    integrity = canary(good['current'])
    http_smoke(int(init()['DEMO_APP_PORT']), authenticated=good.get('seeded', False))
    health = json.loads(output(COMPOSE + ['exec', '-T', 'app', 'php', 'artisan', 'operacoes:saude', '--json'], release=good['current']))
    save_state({**good, 'integrity': integrity, 'health': health['status'], 'updated_at': datetime.now(timezone.utc).isoformat()})
    (LOCAL / ('recovered-' + timestamp() + '.json')).write_text(json.dumps(attempt, indent=2), encoding='utf-8')
    ATTEMPT_FILE.unlink()
    print(json.dumps({'recovered_last_known_good': good['current'], 'data_preserved': True, 'health': health['status']}), flush=True)

def resume_initialization():
    with release_lock():
        if not ATTEMPT_FILE.exists():
            raise RuntimeError('Não há inicialização pendente para retomar.')
        attempt = json.loads(ATTEMPT_FILE.read_text(encoding='utf-8'))
        if attempt.get('operation') != 'deploy' or attempt.get('last_known_good', {}).get('current'):
            raise RuntimeError('resume-init só pode retomar a primeira inicialização, sem versão anterior.')
        if attempt.get('stage') != 'initialization_incomplete':
            raise RuntimeError('O journal não está no estado seguro de retomada da primeira inicialização.')
        if image_identity(attempt['candidate']) != attempt['candidate_image']:
            raise RuntimeError('A imagem da inicialização mudou; o journal foi preservado.')
        assert_dependencies_signature(attempt['candidate'], attempt.get('dependencies_signature'))
        try:
            execute_deploy_attempt(attempt)
        except Exception:
            if ATTEMPT_FILE.exists():
                pending = json.loads(ATTEMPT_FILE.read_text(encoding='utf-8'))
                pending['stage'] = 'initialization_incomplete'
                save_attempt(pending)
            run(COMPOSE + ['stop', *RUNTIME_SERVICES], release=attempt['candidate'], allow_failure=True)
            raise

def rollback():
    with release_lock():
        if ATTEMPT_FILE.exists():
            raise RuntimeError('Há um deploy incompleto. Execute recover para voltar à última versão boa, antes de rollback.')
        current = state()
        target = current.get('previous')
        if not target or image_identity(target) != current.get('previous_image'):
            raise RuntimeError('Artefato anterior não encontrado ou identidade divergente.')
        assert_running_identity(current['current'], current['current_image'])
        assert_dependencies_signature(current['current'], current.get('dependencies_signature'))
        assert_dependencies_signature(target, current.get('dependencies_signature'))
        if current.get('previous_schema') != current.get('integrity', {}).get('schema_signature'):
            raise RuntimeError('O schema mudou entre versões; este rollback automático exige migrations idênticas. Prepare uma correção compatível.')
        attempt = {
            'operation': 'rollback',
            'candidate': target,
            'candidate_image': current['previous_image'],
            'candidate_schema': current['integrity']['schema_signature'],
            'last_known_good': current,
            'dependencies_signature': current['dependencies_signature'],
            'stage': 'prepared',
        }
        save_attempt(attempt)
        try:
            quiesce_runtime(current['current'])
            archive = backup_quiesced(current['current'])
            before = canary(current['current'])
            attempt['backup'] = str(archive.relative_to(ROOT))
            attempt['stage'] = 'runtime_switch_started'
            save_attempt(attempt)
            # Only code is rolled back. Schema and pending durable events remain intact.
            start_runtime(target)
            after = canary(target)
            if before != after:
                raise RuntimeError('Contagens divergentes após rollback; preserve os dados e investigue.')
            http_smoke(int(init()['DEMO_APP_PORT']), authenticated=current.get('seeded', False))
            health = json.loads(output(COMPOSE + ['exec', '-T', 'app', 'php', 'artisan', 'operacoes:saude', '--json'], release=target))
            save_state({**current, 'current': target, 'current_image': current['previous_image'], 'previous': current['current'], 'previous_image': current['current_image'], 'previous_schema': before['schema_signature'], 'integrity': after, 'health': health['status'], 'updated_at': datetime.now(timezone.utc).isoformat()})
            ATTEMPT_FILE.unlink()
            print(json.dumps({'rolled_back_to': target, 'data_preserved': True, 'health': health['status'], **after}), flush=True)
        except Exception:
            try:
                recover_unlocked()
            except Exception:
                print('Recuperação automática do rollback não concluída; journal preservado em .demo/attempt.json.', flush=True)
            raise

def restore(archive_path):
    with release_lock():
        started = time.monotonic()
        archive = Path(archive_path).resolve()
        if not archive.is_relative_to(ROOT / '__FILES/bd'):
            raise RuntimeError('Use um backup deste projeto em __FILES/bd.')
        with zipfile.ZipFile(archive) as source:
            manifest = json.loads(source.read('manifest.json'))
            sql = source.read('biblioteca_demo.sql')
        if manifest.get('database') != 'biblioteca_demo' or manifest.get('environment') != 'docker-demo-local' or hashlib.sha256(sql).hexdigest() != manifest.get('sha256'):
            raise RuntimeError('Origem ou checksum inválido. Nenhum banco foi alterado.')
        release = manifest['release']
        if image_identity(release) != manifest['image_id']:
            raise RuntimeError('A imagem correspondente ao backup não está disponível.')
        database = 'biblioteca_demo_restore_' + timestamp()
        restore_user = 'restore_' + secrets.token_hex(6)
        restore_password = secrets.token_hex(24)
        create = f"CREATE DATABASE `{database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '{restore_user}'@'%' IDENTIFIED BY '{restore_password}'; GRANT ALL ON `{database}`.* TO '{restore_user}'@'%'; GRANT ALL ON `{database}`.* TO 'biblioteca_demo'@'%';"
        run(
            COMPOSE + ['exec', '-T', 'mysql', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -h 127.0.0.1 -u root'],
            release=release,
            input_data=create.encode(),
            sensitive_output=True,
        )
        try:
            # Even an unexpected USE statement in an archive cannot write to the source database.
            run(
                COMPOSE + ['exec', '-T', '-e', 'RESTORE_USER=' + restore_user, '-e', 'RESTORE_PASSWORD=' + restore_password, 'mysql', 'sh', '-c', 'MYSQL_PWD="$RESTORE_PASSWORD" exec mysql -h 127.0.0.1 -u "$RESTORE_USER" ' + database],
                release=release,
                input_data=sql,
                sensitive_output=True,
            )
        finally:
            run(
                COMPOSE + ['exec', '-T', 'mysql', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -h 127.0.0.1 -u root'],
                release=release,
                input_data=f"DROP USER IF EXISTS '{restore_user}'@'%';".encode(),
                sensitive_output=True,
            )
        integrity = canary(release, database)
        if integrity['counts'] != manifest['counts_before_dump']:
            raise RuntimeError('Contagens diferem do pré-dump; confira mudanças concorrentes. A origem foi preservada.')
        container = 'biblioteca-restore-' + timestamp()
        created = False
        try:
            run(COMPOSE + ['run', '-d', '--no-deps', '--name', container, '-p', '127.0.0.1:8091:8080', '-v', database + '_storage:/var/www/html/storage', '-e', 'DB_DATABASE=' + database, '-e', 'CACHE_PREFIX=' + database + ':', '-e', 'REDIS_PREFIX=' + database + ':', '-e', 'SESSION_COOKIE=' + database, '-e', 'MAIL_MAILER=array', 'app'], release=release)
            created = True
            mounts = json.loads(output(['docker', 'inspect', container, '--format', '{{json .Mounts}}']))
            if not any(mount.get('Destination') == '/var/www/html/storage' and mount.get('Name') == database + '_storage' for mount in mounts):
                raise RuntimeError('O storage do ensaio não está isolado.')
            wait_http(8091)
            http_smoke(8091, authenticated=integrity['counts']['users'] > 0)
        finally:
            if created:
                run(['docker', 'stop', '--time', '5', container], allow_failure=True)
                run(['docker', 'rm', container], allow_failure=True)
        run(['docker', 'volume', 'rm', database + '_storage'])
        evidence = {'restored_database': database, 'source_unchanged': True, 'checksum': 'verified', 'storage_isolated': True, 'authenticated_http_canary': integrity['counts']['users'] > 0, 'elapsed_seconds': round(time.monotonic() - started, 2), 'backup_created_at': manifest['created_at'], 'release': release, **integrity}
        path = LOCAL / ('restore-' + timestamp() + '.json')
        path.write_text(json.dumps(evidence, indent=2), encoding='utf-8')
        print(json.dumps(evidence), flush=True)

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['init', 'deploy', 'status', 'backup', 'restore', 'rollback', 'recover', 'resume-init'])
    parser.add_argument('--seed', action='store_true')
    parser.add_argument('--image', help='Use a previously built local biblioteca-demo-app image tag')
    parser.add_argument('--archive', help='Backup archive from __FILES/bd')
    args = parser.parse_args()
    init()
    if args.action == 'init':
        print('.env.demo inicializado; valores existentes preservados. Nenhum segredo foi exibido.')
    elif args.action == 'deploy':
        deploy(args.seed, args.image)
    elif args.action == 'status':
        print(json.dumps(state(), indent=2))
        print(output(COMPOSE + ['ps', '--format', '{{.Service}} {{.State}} {{.Health}}']))
    elif args.action == 'backup':
        with release_lock(): backup()
    elif args.action == 'restore':
        if not args.archive: parser.error('--archive é obrigatório')
        restore(args.archive)
    elif args.action == 'rollback':
        rollback()
    elif args.action == 'recover':
        with release_lock(): recover_unlocked()
    elif args.action == 'resume-init':
        resume_initialization()

if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, subprocess.SubprocessError, OSError, ValueError) as error:
        raise SystemExit(str(error))
