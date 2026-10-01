<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'legal_name' => $name.' Pvt Ltd',
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('98########'),
            'city' => fake()->city(),
            'country' => 'India',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'date_format' => 'd M Y',
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the company has been deactivated by the super admin.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
