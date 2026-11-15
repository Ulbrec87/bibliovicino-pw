<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponse
{
    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     */
    public static function success(
        array $payload,
        int $status = Response::HTTP_OK,
        array $headers = [],
    ): JsonResponse {
        return new JsonResponse($payload, $status, $headers);
    }

    /** @param array<string, string> $headers */
    public static function error(
        string $code,
        string $message,
        int $status,
        array $headers = [],
    ): JsonResponse {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status, $headers);
    }

    /**
     * @param array<string, list<string>> $fields
     * @param array<string, string>       $headers
     */
    public static function validationError(array $fields, array $headers = []): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'validation_failed',
                'message' => 'The submitted data is invalid.',
                'fields' => $fields,
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY, $headers);
    }

    private function __construct()
    {
    }
}
