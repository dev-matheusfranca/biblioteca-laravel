<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_is_paginated_filterable_and_excludes_inactive_books(): void
    {
        [$author, $category, $visible] = PhysicalCatalog::book(['titulo' => 'Arquitetura Limpa', 'isbn' => '9780000000001']);
        [, , $other] = PhysicalCatalog::book(['titulo' => 'Outro Livro', 'isbn' => '9780000000002']);
        [, , $inactive] = PhysicalCatalog::book(['titulo' => 'Arquitetura Antiga', 'status' => 'inativo']);

        $response = $this->getJson('/api/v1/catalogo?q=Arquitetura&categoria='.$category->id.'&per_page=1');

        $response->assertOk()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.autor.id', $author->id)
            ->assertJsonPath('data.0.disponibilidade.disponiveis', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonMissing(['id' => $other->id])
            ->assertJsonMissing(['id' => $inactive->id]);

        $this->getJson('/api/v1/catalogo/'.$visible->id)
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Arquitetura Limpa');
        $this->getJson('/api/v1/catalogo/'.$inactive->id)
            ->assertNotFound()
            ->assertJsonStructure(['message', 'request_id']);
    }

    public function test_catalog_validation_errors_are_consistent_json(): void
    {
        $response = $this->getJson('/api/v1/catalogo?per_page=999');

        $response->assertUnprocessable()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('message', 'Os dados enviados são inválidos.')
            ->assertJsonStructure(['request_id', 'errors' => ['per_page']]);
    }

    public function test_public_catalog_rate_limit_returns_the_standard_error_shape(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77']);
        foreach (range(1, 60) as $attempt) {
            $this->getJson('/api/v1/catalogo')->assertOk();
        }

        $this->getJson('/api/v1/catalogo')
            ->assertTooManyRequests()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('message', 'Limite de requisições excedido.')
            ->assertJsonStructure(['request_id']);
    }
}
