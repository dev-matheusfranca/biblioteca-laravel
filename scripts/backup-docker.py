"""Read-only backup of this project's dedicated Docker development database."""
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import subprocess
import zipfile

root = Path(__file__).resolve().parents[1]
destination = root / '__FILES' / 'bd'
destination.mkdir(parents=True, exist_ok=True)
timestamp = datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S-%f')
archive = destination / f'biblioteca_biblioteca_dev_local_{timestamp}.sql.zip'
command = ['docker', 'compose', '--env-file', '.env.docker', 'exec', '-T', 'mysql',
           'sh', '-c', 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump -h 127.0.0.1 -u biblioteca '
           '--single-transaction --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF biblioteca_dev']
result = subprocess.run(command, cwd=root, capture_output=True)
if result.returncode or not result.stdout:
    raise SystemExit('Backup falhou; confira o serviço MySQL dedicado. Nenhum dump foi exibido.')
with zipfile.ZipFile(archive, 'x', compression=zipfile.ZIP_DEFLATED) as output:
    output.writestr('biblioteca_dev.sql', result.stdout)
    output.writestr('manifest.json', json.dumps({
        'database': 'biblioteca_dev', 'environment': 'docker-local', 'created_at': timestamp,
        'sha256': hashlib.sha256(result.stdout).hexdigest(), 'bytes': len(result.stdout),
    }, indent=2))
with zipfile.ZipFile(archive) as output:
    if output.testzip() is not None:
        raise SystemExit('Falha na integridade do ZIP de backup.')
print(json.dumps({'backup': str(archive), 'bytes': archive.stat().st_size,
                  'database_changed': False, 'integrity': 'ok'}, ensure_ascii=True))
