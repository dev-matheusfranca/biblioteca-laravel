import { randomBytes } from 'node:crypto';
import { writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const destination = fileURLToPath(new URL('../.env.docker', import.meta.url));
const values = {
    DOCKER_APP_PORT: '8088',
    DOCKER_MAIL_PORT: '8028',
    DOCKER_APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    DOCKER_DB_PASSWORD: randomBytes(24).toString('hex'),
    DOCKER_DB_ROOT_PASSWORD: randomBytes(24).toString('hex'),
    DOCKER_TEST_DB_PASSWORD: randomBytes(24).toString('hex'),
};
try {
    await writeFile(destination, `${Object.entries(values).map(([key, value]) => `${key}=${value}`).join('\n')}\n`, { flag: 'wx', mode: 0o600 });
    console.log('.env.docker criado. O ambiente existente foi preservado.');
} catch (error) {
    if (error.code !== 'EEXIST') throw error;
    console.log('.env.docker já existe e foi preservado.');
}
