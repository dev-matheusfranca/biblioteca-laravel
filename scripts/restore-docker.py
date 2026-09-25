"""Restore a verified local backup into a NEW Docker rehearsal database only."""
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import zipfile

root = Path(__file__).resolve().parents[1]
if len(sys.argv) != 2:
    raise SystemExit('Uso: python scripts/restore-docker.py caminho-do-backup.sql.zip')
archive = Path(sys.argv[1]).resolve()
if not archive.is_relative_to(root / '__FILES' / 'bd'):
    raise SystemExit('Use um backup do diretório __FILES/bd deste projeto.')
with zipfile.ZipFile(archive) as backup:
    manifest = json.loads(backup.read('manifest.json'))
    sql = backup.read('biblioteca_dev.sql')
if manifest.get('database') != 'biblioteca_dev' or manifest.get('environment') != 'docker-local':
    raise SystemExit('Este restaurador aceita somente backups da demonstração Docker local.')
if hashlib.sha256(sql).hexdigest() != manifest.get('sha256'):
    raise SystemExit('Checksum do backup inválido. Nada foi importado.')
database = 'biblioteca_restore_' + datetime.now(timezone.utc).strftime('%Y%m%d%H%M%S%f')
base = ['docker', 'compose', '--env-file', '.env.docker', 'exec', '-T', 'mysql', 'sh', '-c']
create = f"CREATE DATABASE `{database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON `{database}`.* TO 'biblioteca'@'%';"
command = base + ['MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -h 127.0.0.1 -u root']
result = subprocess.run(command, input=create.encode(), cwd=root, capture_output=True)
if result.returncode:
    raise SystemExit('Não foi possível criar o banco exclusivo de ensaio. Nenhum banco existente foi sobrescrito.')
command = base + [f'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -h 127.0.0.1 -u biblioteca {database}']
result = subprocess.run(command, input=sql, cwd=root, capture_output=True)
if result.returncode:
    raise SystemExit(f'Importação incompleta no banco exclusivo {database}. O banco de origem foi preservado.')
print(json.dumps({'restored_database': database, 'source_database_changed': False,
                  'checksum': 'verified', 'source_bytes': len(sql)}))
