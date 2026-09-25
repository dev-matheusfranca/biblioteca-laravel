<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiErrorResponse
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, string>  $headers
     */
    public static function make(Request $request, string $message, int $status, array $errors = [], array $headers = []): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
            $request->attributes->set('request_id', $requestId);
        }
        $body = ['message' => $message, 'request_id' => $requestId];
        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status, ['X-Request-Id' => $requestId, ...$headers]);
    }
}
