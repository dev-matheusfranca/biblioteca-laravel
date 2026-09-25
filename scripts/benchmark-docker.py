"""Create an isolated synthetic database and compare the same image with cache off/on."""
from datetime import datetime, timezone
import json
import os
from pathlib import Path
import subprocess
import time
import urllib.request

root = Path(__file__).resolve().parents[1]
stamp = datetime.now(timezone.utc).strftime('%Y%m%d%H%M%S%f')
database = 'biblioteca_benchmark_' + stamp
compose = ['docker', 'compose', '--env-file', '.env.docker']

def run(args, **kwargs):
    result = subprocess.run(args, cwd=root, capture_output=True, **kwargs)
    if result.returncode:
        # Child output can contain runtime configuration. Keep it in the ignored local evidence directory.
        (root / 'output/testing/benchmark-error.log').write_bytes(result.stderr + result.stdout)
        raise RuntimeError('Falha no ensaio; consulte output/testing/benchmark-error.log localmente.')
    return result.stdout.decode('utf-8', errors='replace')

(root / 'output/testing').mkdir(parents=True, exist_ok=True)
run(compose + ['exec', '-T', 'mysql', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -h 127.0.0.1 -u root'],
    input=f"CREATE DATABASE `{database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON `{database}`.* TO 'biblioteca'@'%';".encode())
environment = ['-e', 'DB_DATABASE=' + database, '-e', 'REDIS_PREFIX=' + database + ':', '-e', 'CACHE_PREFIX=' + database + ':']
run(compose + ['run', '--rm', '--no-deps'] + environment + ['app', 'php', 'artisan', 'migrate', '--force'])
run(compose + ['run', '--rm', '--no-deps'] + environment + ['app', 'php', 'artisan', 'db:seed', '--class=BenchmarkSeeder', '--force'])
report = {'created_at_utc': stamp, 'database': database, 'dataset': {'books': 5000, 'copies': 15000, 'readers': 200, 'closed_loans': 20000},
          'docker_resources': json.loads(run(['docker', 'info', '--format', '{"cpus":{{.NCPU}},"memory_bytes":{{.MemTotal}}}'])),
          'image_id': run(['docker', 'image', 'inspect', 'biblioteca-dev-app', '--format', '{{.Id}}']).strip(), 'scenarios': []}
report['query_probe'] = json.loads(run(compose + ['run', '--rm', '--no-deps'] + environment + ['app', 'php', 'scripts/catalog-query-probe.php']))
for enabled in [False, True]:
    name = 'biblioteca-benchmark-' + stamp
    container = run(compose + ['run', '-d', '--no-deps', '--name', name, '-p', '127.0.0.1:8090:8000'] + environment
                    + ['-e', 'CATALOG_CACHE_ENABLED=' + str(enabled).lower(), 'app']).strip().splitlines()[-1]
    try:
        for attempt in range(40):
            try:
                with urllib.request.urlopen('http://127.0.0.1:8090/up', timeout=2) as response:
                    if response.status == 200: break
            except Exception: time.sleep(0.5)
        else: raise RuntimeError('Aplicação de benchmark não iniciou.')
        before = run(['docker', 'stats', '--no-stream', '--format', '{{json .}}', container]).strip()
        result = json.loads(run(['node', 'scripts/load-catalog.mjs'], env={**os.environ, 'BENCHMARK_URL': 'http://127.0.0.1:8090'}))
        after = run(['docker', 'stats', '--no-stream', '--format', '{{json .}}', container]).strip()
        report['scenarios'].append({'cache_enabled': enabled, 'http': result, 'resources_before': json.loads(before), 'resources_after': json.loads(after)})
        if not enabled:
            # Set the target from the measured baseline before the optimized scenario runs.
            report['target'] = {'p95_ms_max': round(result['p95_ms'] * 0.8, 2), 'errors': 0, 'basis': '20% below the uncached baseline on this local workload'}
        print(json.dumps({'cache_enabled': enabled, **result}), flush=True)
    finally:
        subprocess.run(['docker', 'stop', '--time', '5', container], cwd=root, capture_output=True)
        subprocess.run(['docker', 'rm', container], cwd=root, capture_output=True)
target = root / 'output/testing' / ('benchmark-' + stamp + '.json')
target.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding='utf-8')
print(json.dumps({'evidence': str(target), 'isolated_database_retained': database}))
