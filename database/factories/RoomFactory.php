<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->word(),
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
