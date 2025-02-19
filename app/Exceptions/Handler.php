<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->renderable(function (Throwable $e, $request) {
            if (!$request->is('api/*')) {
                return;
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'error' => true,
                    'message' => 'Unauthenticated.',
                    'status_code' => 401
                ], 401);
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'error' => true,
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                    'status_code' => 422
                ], 422);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'error' => true,
                    'message' => 'API endpoint not found.',
                    'status_code' => 404
                ], 404);
            }

            if ($e instanceof ThrottleRequestsException) {
                return response()->json([
                    'error' => true,
                    'message' => 'Too many requests. Please try again later.',
                    'status_code' => 429
                ], 429);
            }

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        });
    }
}
