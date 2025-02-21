<?php

namespace App\Exceptions;

use App\Services\DynamicLogger;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{

    public function __construct(
        protected DynamicLogger $logger,
        protected ApiExceptionHandler $apiHandler
    ) {
        parent::__construct(app());
    }

    public function register(): void
    {
        $this->renderable(function (Throwable $e, $request) {
            return $this->apiHandler->handle($e, $request);
        });
    }}
