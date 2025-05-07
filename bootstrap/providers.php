<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\JetstreamServiceProvider::class,
    App\Providers\MiddlewareProvider::class,
    App\Providers\S3AssumeRoleServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    App\Providers\ApiServiceProvider::class,
    Modules\Cesop\Providers\CesopServiceProvider::class,
];
