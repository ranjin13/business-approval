<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['draft', 'pending_approval', 'approved', 'rejected'];
        $status = $this->faker->randomElement($statuses);
        $createdBy = User::inRandomOrder()->first()->id ?? User::factory()->create()->id;
        $approvedBy = null;
        $approvedAt = null;

        if ($status === 'approved' || $status === 'rejected') {
            $approvedBy = User::inRandomOrder()->first()->id ?? User::factory()->create()->id;
            $approvedAt = $status === 'approved' ? now() : null;
        }

        // Generate a unique order number
        $nextId = $this->faker->unique()->numberBetween(1, 10000);
        $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        return [
            'order_number' => $orderNumber,
            'status' => $status,
            'total_amount' => $this->faker->randomFloat(2, 100, 2000),
            'notes' => $this->faker->paragraph(),
            'created_by' => $createdBy,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedAt,
        ];
    }

    /**
     * Indicate that the order is in draft status.
     *
     * @return static
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    /**
     * Indicate that the order is pending approval.
     *
     * @return static
     */
    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_approval',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    /**
     * Indicate that the order is approved.
     *
     * @return static
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::inRandomOrder()->first()->id ?? User::factory()->create()->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the order is rejected.
     *
     * @return static
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => User::inRandomOrder()->first()->id ?? User::factory()->create()->id,
            'approved_at' => null,
        ]);
    }

    /**
     * Indicate that the order requires approval (total amount >= 1000).
     *
     * @return static
     */
    public function requiresApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_amount' => $this->faker->randomFloat(2, 1000, 5000),
        ]);
    }
}
