<?php

namespace App\Exceptions;

use App\Services\DynamicLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler
{
    public function __construct(protected DynamicLogger $logger)
    {
        ;
    }

    public function handle(Throwable $exception, $request): ?JsonResponse
    {
        if (!$request->is('api/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof ThrottleRequestsException => $this->handleThrottleException($exception),
            $exception instanceof AuthenticationException => $this->handleAuthenticationException($exception),
            $exception instanceof NotFoundHttpException => $this->handleNotFoundException($exception),
            $exception instanceof ModelNotFoundException => $this->handleModelNotFoundException($exception),
            $exception instanceof ValidationException => $this->handleValidationException($exception),
            $exception instanceof AccessDeniedHttpException => $this->handleAccessDeniedException($exception),
            $exception instanceof QueryException => $this->handleQueryException($exception),
            default => $this->handleDefaultException($exception)
        };
    }

    protected function handleThrottleException(ThrottleRequestsException $e): JsonResponse
    {
        $this->logException($e, 'error', 'Rate limit exceeded');
        return response()->json([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'error' => 'rate_limit_exceeded'
        ], 429);
    }

    protected function handleAuthenticationException(AuthenticationException $e): JsonResponse
    {
        $this->logException($e, 'error', 'Authentication failed');
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated or invalid token',
            'error' => 'authentication_failed'
        ], 401);
    }

    protected function handleNotFoundException(NotFoundHttpException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'The requested resource could not be found',
                'target' => request()->path(),
                'details' => [
                    [
                        'code' => 'RESOURCE_MISSING',
                        'message' => 'The requested endpoint or resource does not exist',
                        'target' => request()->path()
                    ]
                ]
            ]
        ], 404);
    }

    protected function handleModelNotFoundException(ModelNotFoundException $e): JsonResponse
    {
        $this->logException($e, 'info', 'Model not found', [
            'model' => class_basename($e->getModel())
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Requested resource does not exist',
            'error' => 'model_not_found',
            'model' => class_basename($e->getModel())
        ], 404);
    }

    protected function handleValidationException(ValidationException $e): JsonResponse
    {
        $this->logException($e, 'info', 'Validation failed', [
            'errors' => $e->errors()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'The given data was invalid',
            'error' => 'validation_failed',
            'errors' => $e->errors()
        ], 422);
    }

    protected function handleAccessDeniedException(AccessDeniedHttpException $e): JsonResponse
    {
        $this->logException($e, 'error', 'Access denied attempt');
        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to access this resource',
            'error' => 'access_denied'
        ], 403);
    }

    protected function handleQueryException(QueryException $e): JsonResponse
    {
        $this->logException($e, 'error', 'Database error occurred', [
            'sql' => app()->environment('local') ? $e->getSql() : null
        ]);
        return response()->json([
            'success' => false,
            'message' => app()->environment('local') ? $e->getMessage() : 'Database operation failed',
            'error' => 'database_error'
        ], 500);
    }

    protected function handleDefaultException(Throwable $e): JsonResponse
    {
        $this->logException($e, 'error', 'Unhandled exception occurred');

        return response()->json([
            'success' => false,
            'message' => app()->environment('local') ?
                $e->getMessage() : 'An unexpected error occurred',
            'error' => class_basename($e),
            'debug' => app()->environment('local') ? [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ] : null
        ], 500);
    }

    protected function logException(
        Throwable $e,
        string $level,
        string $message,
        array $additionalContext = []
    ): void {
        $context = array_merge([
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'input' => $this->sanitizeInput(request()->all()),
            'trace' => $e->getTraceAsString()
        ], $additionalContext);

        $this->logger->log($level, $message, $context);
    }

    protected function sanitizeInput(array $input): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'credit_card', 'api_key'];

        return collect($input)->map(function ($value, $key) use ($sensitiveFields) {
            if (in_array($key, $sensitiveFields)) {
                return '********';
            }
            return $value;
        })->toArray();
    }
}
