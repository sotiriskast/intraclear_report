<?php
namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->renderable(function (ThrottleRequestsException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'error' => 'rate_limit_exceeded'
            ], 429);
        });

        // Add handler for authentication exceptions
        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated or invalid token',
                    'error' => 'authentication_failed'
                ], 401);
            }
        });

        $this->renderable(function (NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'error' => 'not_found'
            ], 404);
        });

        // Make sure API routes always return JSON
        $this->renderable(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                $status = method_exists($e, 'getStatusCode') ?
                    $e->getStatusCode() : 500;

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => class_basename($e),
                ], $status);
            }
        });
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or invalid token',
                'error' => 'authentication_failed'
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}
