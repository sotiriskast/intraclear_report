<?php

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->adminRole = Role::create(['name' => 'admin']);
    }

    public function test_create_user()
    {
        $repository = app(UserRepository::class);

        $user = $repository->createUser(
            'John Doe',
            'john@example.com',
            'password',
            $this->adminRole->id
        );

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_update_user()
    {
        $user = User::factory()->create();
        $repository = app(UserRepository::class);

        $updatedUser = $repository->updateUser($user, 'Jane Doe', 'jane@example.com', null, $this->adminRole->id );

        $this->assertEquals('Jane Doe', $updatedUser->name);
        $this->assertEquals('jane@example.com', $updatedUser->email);
        $this->assertTrue($updatedUser->hasRole('admin'));
    }

    public function test_delete_user()
    {
        $user = User::factory()->create();
        $repository = app(UserRepository::class);

        $result = $repository->deleteUser($user);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
