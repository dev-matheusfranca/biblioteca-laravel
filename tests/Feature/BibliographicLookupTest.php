<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class BibliographicLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_guest_is_redirected_and_reader_cannot_use_bibliographic_lookup(): void
    {
        $this->get(route('isbn.index'))->assertRedirect(route('login'));
        $this->post(route('isbn.lookup'), ['isbn' => '9780140328721'])->assertRedirect(route('login'));

        $reader = User::factory()->reader()->create();

        $this->actingAs($reader)->get(route('isbn.index'))->assertForbidden();
        $this->actingAs($reader)->post(route('isbn.lookup'), ['isbn' => '9780140328721'])->assertForbidden();
        $this->actingAs($reader)->post(route('isbn.useSuggestion'), ['suggestion_id' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_invalid_isbn_checksum_does_not_make_an_http_request(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->librarian()->create())
            ->post(route('isbn.lookup'), ['isbn' => '9780140328722'])
            ->assertSessionHasErrors('isbn');

        Http::assertNothingSent();
        $this->assertDatabaseCount('livros', 0);
    }

    public function test_lookup_uses_edition_metadata_and_caches_the_normalized_isbn(): void
    {
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::response($this->foundPayload()),
        ]);
        $librarian = User::factory()->librarian()->create();

        $this->actingAs($librarian)
            ->post(route('isbn.lookup'), ['isbn' => '978-0140328721'])
            ->assertOk()
            ->assertViewIs('livros.isbn')
            ->assertViewHas('result', fn (array $result): bool => $result['status'] === 'found'
                && $result['edition']['olid'] === 'OL7353617M'
                && $result['edition']['title'] === 'Edição específica'
                && $result['edition']['publication_year'] === 1988
                && $result['edition']['isbn'] === '9780140328721'
                && $result['authors'] === ['Autor']
                && $result['source_url'] === 'https://openlibrary.org/books/OL7353617M');

        $this->actingAs($librarian)
            ->post(route('isbn.lookup'), ['isbn' => '9780140328721'])
            ->assertOk()
            ->assertViewHas('result', fn (array $result): bool => $result['status'] === 'found');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://openlibrary.org/search.json?')
                && ($query['q'] ?? null) === 'isbn:9780140328721'
                && ($query['limit'] ?? null) === '1';
        });
        $this->assertDatabaseCount('livros', 0);
    }

    public function test_not_found_keeps_manual_catalog_creation_available_without_writing_a_book(): void
    {
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::response([], 404),
        ]);
        $librarian = User::factory()->librarian()->create();

        $this->actingAs($librarian)
            ->post(route('isbn.lookup'), ['isbn' => '9780140328721'])
            ->assertOk()
            ->assertViewIs('livros.isbn')
            ->assertViewHas('result', fn (array $result): bool => $result['status'] === 'not_found');

        $this->actingAs($librarian)->get(route('livros.create'))->assertOk();
        $this->assertDatabaseCount('livros', 0);
    }

    public function test_invalid_response_or_wrong_edition_is_rejected_without_writing_a_book(): void
    {
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::response([
                'numFound' => 1,
                'docs' => [[
                    'key' => '/works/OL45804W',
                    'title' => 'Obra',
                    'author_name' => ['Autor'],
                    'editions' => [
                        'numFound' => 1,
                        'docs' => [[
                            'key' => '/books/OL7353617M',
                            'title' => 'Edição de outro ISBN',
                            'publish_year' => [1988],
                            'isbn' => ['9780000000002'],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs(User::factory()->librarian()->create())
            ->post(route('isbn.lookup'), ['isbn' => '9780140328721'])
            ->assertOk()
            ->assertViewHas('result', fn (array $result): bool => $result['status'] === 'invalid_response');

        Cache::flush();
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::response('{not-json', 200),
        ]);

        $this->actingAs(User::factory()->librarian()->create())
            ->post(route('isbn.lookup'), ['isbn' => '9780140328721'])
            ->assertOk()
            ->assertViewHas('result', fn (array $result): bool => $result['status'] === 'invalid_response');

        $this->assertDatabaseCount('livros', 0);
    }

    public function test_external_title_is_html_escaped_and_only_remains_a_preview(): void
    {
        $payload = $this->foundPayload();
        $payload['docs'][0]['editions']['docs'][0]['title'] = '<script>alert("metadata")</script>';
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::response($payload),
        ]);

        $this->actingAs(User::factory()->librarian()->create())
            ->post(route('isbn.lookup'), ['isbn' => '9780140328721'])
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;metadata&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("metadata")</script>', false);

        $this->assertDatabaseCount('livros', 0);
    }

    public function test_transient_gateway_failure_is_retried_once_before_returning_the_edition(): void
    {
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::sequence()
                ->pushStatus(504)
                ->push($this->foundPayload()),
        ]);

        $this->actingAs(User::factory()->librarian()->create())
            ->post(route('isbn.lookup'), ['isbn' => '9780140328721'])
            ->assertOk()
            ->assertViewHas('result', fn (array $result): bool => $result['status'] === 'found');

        Http::assertSentCount(2);
        $this->assertDatabaseCount('livros', 0);
    }

    public function test_using_suggestion_ignores_client_metadata_consumes_session_and_does_not_write_a_book(): void
    {
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::response($this->foundPayload()),
        ]);
        $librarian = User::factory()->librarian()->create();

        $this->actingAs($librarian)
            ->post(route('isbn.lookup'), ['isbn' => '9780140328721'])
            ->assertOk();
        $suggestion = $this->app['session']->get('bibliographic_suggestion');

        $this->assertIsArray($suggestion);

        $this->actingAs($librarian)
            ->post(route('isbn.useSuggestion'), [
                'suggestion_id' => $suggestion['id'],
                'titulo' => 'Título adulterado pelo cliente',
                'ano_publicacao' => 1900,
                'isbn' => '9780000000002',
                'authors' => ['Autor adulterado'],
            ])
            ->assertRedirect(route('livros.create'))
            ->assertSessionHasInput('titulo', 'Edição específica')
            ->assertSessionHasInput('ano_publicacao', 1988)
            ->assertSessionHasInput('isbn', '9780140328721')
            ->assertSessionMissing('bibliographic_suggestion');

        $this->assertDatabaseCount('livros', 0);
    }

    public function test_expired_or_tampered_suggestion_is_rejected_without_writing_a_book(): void
    {
        $librarian = User::factory()->librarian()->create();
        $suggestion = [
            'id' => (string) Str::uuid(),
            'created_at' => now()->subSeconds(901)->timestamp,
            'result' => [
                'status' => 'found',
                'edition' => ['title' => 'Edição específica', 'publication_year' => 1988, 'isbn' => '9780140328721'],
            ],
        ];

        $this->actingAs($librarian)
            ->withSession(['bibliographic_suggestion' => $suggestion])
            ->post(route('isbn.useSuggestion'), ['suggestion_id' => (string) Str::uuid()])
            ->assertSessionHasErrors('isbn');

        $this->actingAs($librarian)
            ->withSession(['bibliographic_suggestion' => $suggestion])
            ->post(route('isbn.useSuggestion'), ['suggestion_id' => $suggestion['id']])
            ->assertSessionHasErrors('isbn');

        $this->assertDatabaseCount('livros', 0);
    }

    /** @return array<string, mixed> */
    private function foundPayload(): array
    {
        return [
            'numFound' => 1,
            'docs' => [[
                'key' => '/works/OL45804W',
                'title' => 'Obra',
                'author_name' => ['Autor'],
                'editions' => [
                    'numFound' => 1,
                    'docs' => [[
                        'key' => '/books/OL7353617M',
                        'title' => 'Edição específica',
                        'publish_year' => [1988],
                        'isbn' => ['9780140328721', '0140328726'],
                    ]],
                ],
            ]],
        ];
    }
}
