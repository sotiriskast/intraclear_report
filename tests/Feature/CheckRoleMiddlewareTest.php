<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CheckRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles required for tests
        \Spatie\Permission\Models\Role::create(['name' => 'admin']);
        \Spatie\Permission\Models\Role::create(['name' => 'super-admin']);
    }

    public function test_super_admin_can_access_protected_route()
    {
        $middleware = new CheckRole();

        // Create a super-admin user
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        // Mock a request
        $request = Request::create('/test-protected-route', 'GET');
        $request->setUserResolver(fn() => $user);

        // Pass the request through the middleware
        $response = $middleware->handle($request, function () {
            return new Response('Access granted', 200);
        }, 'admin');

        // Assert the response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Access granted', $response->getContent());
    }

    public function test_admin_can_access_protected_route()
    {
        $middleware = new CheckRole();

        // Create an admin user
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Mock a request
        $request = Request::create('/test-protected-route', 'GET');
        $request->setUserResolver(fn() => $user);

        // Pass the request through the middleware
        $response = $middleware->handle($request, function () {
            return new Response('Access granted', 200);
        }, 'admin');

        // Assert the response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Access granted', $response->getContent());
    }

    public function test_user_without_role_is_rejected()
    {
        $middleware = new CheckRole();

        // Create a user without any role
        $user = User::factory()->create();

        // Mock a request
        $request = Request::create('/test-protected-route', 'GET');
        $request->setUserResolver(fn() => $user);

        // Expect the middleware to throw an exception
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Unauthorized action.');

        $middleware->handle($request, function () {
            return new Response('Access granted', 200);
        }, 'admin');
    }

    public function test_unauthenticated_user_is_rejected()
    {
        $middleware = new CheckRole();

        // Mock a request with no authenticated user
        $request = Request::create('/test-protected-route', 'GET');
        $request->setUserResolver(fn() => null);

        // Expect the middleware to throw an exception
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Unauthorized action.');

        $middleware->handle($request, function () {
            return new Response('Access granted', 200);
        }, 'admin');
    }
}
