<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function currentUser(): ?User
    {
        return Auth::user();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->users->all();
    }

    public function findById(string $id): ?User
    {
        return $this->users->findById($id);
    }

    /**
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! password_verify($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return [$user, $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
