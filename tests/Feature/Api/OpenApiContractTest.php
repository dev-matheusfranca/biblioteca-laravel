<?php

namespace Tests\Feature\Api;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    public function test_openapi_contract_covers_every_v1_route_and_standard_responses(): void
    {
        $contract = Yaml::parseFile(base_path('docs/openapi.yaml'));
        $this->assertSame('3.1.0', $contract['openapi']);

        $operations = [
            'get /catalogo' => [200, 422, 429],
            'get /catalogo/{livro}' => [200, 404, 429],
            'get /me' => [200, 401, 403, 429],
            'get /me/emprestimos' => [200, 401, 403, 422, 429],
            'get /me/emprestimos/{locacao}' => [200, 401, 403, 404, 429],
            'get /me/reservas' => [200, 401, 403, 422, 429],
            'post /livros/{livro}/reservas' => [201, 401, 403, 404, 409, 422, 429],
            'delete /reservas/{reserva}' => [200, 401, 403, 404, 409, 422, 429],
            'post /emprestimos/{locacao}/renovacoes' => [200, 401, 403, 404, 409, 422, 429],
        ];

        foreach ($operations as $operation => $statuses) {
            [$method, $path] = explode(' ', $operation, 2);
            $this->assertArrayHasKey($path, $contract['paths'], $operation);
            $this->assertArrayHasKey($method, $contract['paths'][$path], $operation);
            foreach ($statuses as $status) {
                $this->assertArrayHasKey((string) $status, $contract['paths'][$path][$method]['responses'], "{$operation} {$status}");
            }
        }

        foreach (['Book', 'User', 'Loan', 'Reservation', 'Error'] as $schema) {
            $this->assertArrayHasKey($schema, $contract['components']['schemas']);
        }

        $requiredAbilities = [
            'get /me' => 'personal:read',
            'get /me/emprestimos' => 'personal:read',
            'get /me/emprestimos/{locacao}' => 'personal:read',
            'get /me/reservas' => 'personal:read',
            'post /livros/{livro}/reservas' => 'reservations:write',
            'delete /reservas/{reserva}' => 'reservations:write',
            'post /emprestimos/{locacao}/renovacoes' => 'loans:renew',
        ];
        foreach ($requiredAbilities as $operation => $ability) {
            [$method, $path] = explode(' ', $operation, 2);
            $this->assertSame($ability, $contract['paths'][$path][$method]['x-required-ability']);
        }
    }

    public function test_documented_operations_match_the_registered_api_routes(): void
    {
        $expected = [
            'GET api/v1/catalogo',
            'GET api/v1/catalogo/{livro}',
            'GET api/v1/me',
            'GET api/v1/me/emprestimos',
            'GET api/v1/me/emprestimos/{locacao}',
            'GET api/v1/me/reservas',
            'POST api/v1/livros/{livro}/reservas',
            'DELETE api/v1/reservas/{reserva}',
            'POST api/v1/emprestimos/{locacao}/renovacoes',
        ];
        $actual = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'api.v1.'))
            ->map(fn ($route) => collect($route->methods())->first(fn (string $method) => $method !== 'HEAD').' '.$route->uri())
            ->sort()
            ->values()
            ->all();
        sort($expected);

        $this->assertSame($expected, $actual);
    }
}
