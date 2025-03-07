<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['draft', 'pending_approval', 'approved', 'rejected'];
        $fromStatus = $this->faker->randomElement(array_merge([null], $statuses));
        $toStatus = $this->faker->randomElement($statuses);

        // Ensure from_status and to_status are different
        while ($fromStatus !== null && $fromStatus === $toStatus) {
            $toStatus = $this->faker->randomElement($statuses);
        }

        return [
            'order_id' => Order::factory(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => User::inRandomOrder()->first()->id ?? User::factory()->create()->id,
            'comments' => $this->faker->optional(0.7)->sentence(),
        ];
    }

    /**
     * Define a status change to draft.
     *
     * @return static
     */
    public function toDraft(): static
    {
        return $this->state(fn (array $attributes) => [
            'to_status' => 'draft',
        ]);
    }

    /**
     * Define a status change to pending approval.
     *
     * @return static
     */
    public function toPendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => 'draft',
            'to_status' => 'pending_approval',
        ]);
    }

    /**
     * Define a status change to approved.
     *
     * @return static
     */
    public function toApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => 'pending_approval',
            'to_status' => 'approved',
            'comments' => $this->faker->optional(0.9)->sentence(),
        ]);
    }

    /**
     * Define a status change to rejected.
     *
     * @return static
     */
    public function toRejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => 'pending_approval',
            'to_status' => 'rejected',
            'comments' => $this->faker->sentence(), // Always include a comment for rejection
        ]);
    }
}
