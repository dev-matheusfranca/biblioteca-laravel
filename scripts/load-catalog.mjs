import { performance } from 'node:perf_hooks';

const base = new URL(process.env.BENCHMARK_URL ?? 'http://127.0.0.1:8090');
if (!['localhost', '127.0.0.1'].includes(base.hostname)) throw new Error('O ensaio aceita somente o ambiente local.');
const count = Number(process.env.BENCHMARK_REQUESTS ?? 200);
const concurrency = Number(process.env.BENCHMARK_CONCURRENCY ?? 4);
if (!Number.isInteger(count) || count < 1 || count > 5000 || !Number.isInteger(concurrency) || concurrency < 1 || concurrency > 20) throw new Error('Carga fora dos limites permitidos.');
const paths = ['/catalogo', '/catalogo?q=Acervo', '/catalogo?categoria=5', '/catalogo?page=10'];
async function request(path) {
  const start = performance.now();
  try {
    const response = await fetch(new URL(path, base), { signal: AbortSignal.timeout(10000) });
    const body = await response.text();
    return { ms: performance.now() - start, ok: response.status === 200 && body.includes('Catálogo público'), status: response.status };
  } catch { return { ms: performance.now() - start, ok: false, status: 0 }; }
}
for (const path of paths) await request(path);
let cursor = 0;
const samples = [];
const started = performance.now();
await Promise.all(Array.from({ length: concurrency }, async () => {
  while (cursor < count) { const index = cursor++; samples.push(await request(paths[index % paths.length])); }
}));
const seconds = (performance.now() - started) / 1000;
const sorted = samples.map(sample => sample.ms).sort((a, b) => a - b);
const percentile = p => Math.round(sorted[Math.ceil(sorted.length * p) - 1] * 100) / 100;
const result = { requests: count, concurrency, paths, p50_ms: percentile(.5), p95_ms: percentile(.95), max_ms: sorted.at(-1), errors: samples.filter(sample => !sample.ok).length, elapsed_seconds: seconds, requests_per_second: count / seconds };
console.log(JSON.stringify(result, null, 2));
if (result.errors) process.exitCode = 1;
