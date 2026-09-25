<?php

namespace App\Services\Bibliography;

use App\Services\CorrelationId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class OpenLibraryLookup
{
    private const FIELDS = 'key,title,author_name,editions,editions.key,editions.title,editions.publish_year,editions.isbn';

    public function lookup(string $isbn): array
    {
        if (Isbn::normalize($isbn) !== $isbn) {
            return ['status' => 'invalid_response'];
        }
        $cacheKey = 'openlibrary:edition:v1:'.$isbn;
        if (is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }
        $lock = Cache::lock('openlibrary:network', 15);
        if (! $lock->get()) {
            return ['status' => 'busy'];
        }

        try {
            if (is_array($cached = Cache::store()->get($cacheKey))) {
                return $cached;
            }
            if (microtime(true) - (float) Cache::get('openlibrary:last-request', 0) < 1) {
                return ['status' => 'busy'];
            }
            $result = $this->request($isbn);
            if (in_array($result['status'], ['found', 'not_found'], true)) {
                Cache::put($cacheKey, $result, $result['status'] === 'found' ? now()->addDays(7) : now()->addHour());
            }

            return $result;
        } catch (Throwable) {
            Log::warning('Bibliographic provider unavailable', [
                'provider' => 'open_library', 'error_code' => 'provider_unavailable',
                'correlation_id' => app(CorrelationId::class)->current(),
            ]);

            return ['status' => 'unavailable'];
        } finally {
            $lock->release();
        }
    }

    private function request(string $isbn): array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if ($attempt > 0) {
                usleep(1000000);
            }
            Cache::put('openlibrary:last-request', microtime(true), 60);
            try {
                $response = Http::acceptJson()
                    ->withUserAgent('BibliotecaPortfolio/1.0')
                    ->connectTimeout(2)->timeout(5)
                    ->withOptions([
                        'allow_redirects' => false,
                        'on_headers' => static function (ResponseInterface $response): void {
                            if ((int) $response->getHeaderLine('Content-Length') > 65536) {
                                throw new RuntimeException('Bibliographic response too large.');
                            }
                        },
                        'progress' => static function ($total, $downloaded): void {
                            if ($downloaded > 65536) {
                                throw new RuntimeException('Bibliographic response too large.');
                            }
                        },
                    ])
                    ->get('https://openlibrary.org/search.json', ['q' => 'isbn:'.$isbn, 'fields' => self::FIELDS, 'limit' => 1]);
            } catch (ConnectionException $exception) {
                if ($attempt === 0) {
                    continue;
                }
                throw $exception;
            }
            if ($attempt === 0 && in_array($response->status(), [500, 502, 503, 504], true)) {
                continue;
            }
            if ($response->status() === 404) {
                return ['status' => 'not_found'];
            }
            if (! $response->successful()) {
                return ['status' => 'unavailable'];
            }
            if (strlen($response->body()) > 65536) {
                return ['status' => 'invalid_response'];
            }

            return $this->parse($response->json(), $isbn);
        }

        return ['status' => 'unavailable'];
    }

    private function parse(mixed $data, string $isbn): array
    {
        if (! is_array($data) || ! isset($data['numFound']) || ! is_array($data['docs'] ?? null)) {
            return ['status' => 'invalid_response'];
        }
        if ($data['numFound'] !== 1 || count($data['docs']) !== 1) {
            return ['status' => 'not_found'];
        }
        $work = $data['docs'][0];
        if (! is_array($work) || ! is_array($work['editions'] ?? null) || ($work['editions']['numFound'] ?? null) !== 1) {
            return ['status' => 'not_found'];
        }
        $edition = $work['editions']['docs'][0] ?? null;
        if (! is_array($edition) || ! is_string($edition['key'] ?? null) || ! preg_match('~^/books/OL[0-9]+M$~D', $edition['key'])
            || ! is_string($edition['title'] ?? null) || trim($edition['title']) === '' || mb_strlen($edition['title']) > 255
            || ! is_array($edition['isbn'] ?? null) || ! in_array($isbn, $edition['isbn'], true)) {
            return ['status' => 'invalid_response'];
        }
        $year = $edition['publish_year'][0] ?? null;
        $year = is_int($year) && $year >= 1000 && $year <= now()->year ? $year : null;
        $authors = [];
        foreach (array_slice(is_array($work['author_name'] ?? null) ? $work['author_name'] : [], 0, 5) as $author) {
            if (is_string($author) && mb_strlen($author) <= 150) {
                $authors[] = $author;
            }
        }

        return [
            'status' => 'found',
            'edition' => ['olid' => substr($edition['key'], 7), 'title' => $edition['title'], 'publication_year' => $year, 'isbn' => $isbn],
            'authors' => $authors,
            'source_url' => 'https://openlibrary.org'.$edition['key'],
        ];
    }
}
