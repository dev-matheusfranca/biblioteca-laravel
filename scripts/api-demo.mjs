import { randomUUID } from 'node:crypto';

const baseUrl = new URL(process.env.BIBLIOTECA_API_URL ?? 'http://localhost:8088/api/v1');
if (!['http:', 'https:'].includes(baseUrl.protocol) || baseUrl.username || baseUrl.password) {
    throw new Error('BIBLIOTECA_API_URL deve usar HTTP(S) e não pode conter credenciais.');
}
const token = process.env.BIBLIOTECA_API_TOKEN;
const mutation = process.argv.find((argument) => argument.startsWith('--reserve-book='));
const idempotencyArgument = process.argv.find((argument) => argument.startsWith('--idempotency-key='));
const idempotencyKey = idempotencyArgument?.split('=', 2)[1] ?? randomUUID();
if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(idempotencyKey)) {
    throw new Error('--idempotency-key exige um UUID válido.');
}

async function request(path, options = {}) {
    const headers = { Accept: 'application/json', ...options.headers };
    if (token) headers.Authorization = `Bearer ${token}`;
    const response = await fetch(new URL(path, `${baseUrl.toString().replace(/\/$/, '')}/`), {
        ...options,
        headers,
        signal: AbortSignal.timeout(10_000),
    });
    const body = await response.json();
    if (!response.ok) {
        throw new Error(`API ${response.status}: ${body.message ?? 'falha sem mensagem'}`);
    }
    return body;
}

const catalog = await request('catalogo?per_page=3');
console.log(`Catálogo acessível: ${catalog.data.length} item(ns) nesta página.`);

if (token) {
    const me = await request('me');
    console.log(`Token válido para o leitor #${me.data.id}.`);
}

if (mutation) {
    if (!token) throw new Error('BIBLIOTECA_API_TOKEN é obrigatório para mutações.');
    const bookId = mutation.split('=', 2)[1];
    if (!/^\d+$/.test(bookId)) throw new Error('--reserve-book exige um ID inteiro positivo.');
    const reservation = await request(`livros/${bookId}/reservas`, {
        method: 'POST',
        headers: { 'Idempotency-Key': idempotencyKey },
    });
    console.log(`Reserva #${reservation.data.id} registrada com estado ${reservation.data.status}.`);
}
