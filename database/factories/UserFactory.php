<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Realm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 'USR'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),

            /*
             * A factory user is staff, an employee, and active — the ordinary
             * case, so a test that does not care about realms does not have to
             * say anything about them.
             *
             * The defaults matter more than they look: `staff_kind` is what
             * grants the Employee base (§5), and a factory that left it null
             * would produce accounts with no personal records and quietly
             * broken dashboards in every test that forgot to set it.
             */
            'account_type' => Realm::STAFF,
            'staff_kind' => 'employee',
            'status' => 'active',
        ];
    }

    /**
     * An account that cannot sign in (§4.2 step 4).
     *
     * Holds no permissions at all, however many roles it has — the check runs
     * before roles are read. See Rbac::permissionsFor.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'suspended']);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
