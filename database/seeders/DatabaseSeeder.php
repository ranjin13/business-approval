<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the UserSeeder to create test users
        $this->call([
            UserSeeder::class,
        ]);

        // Create 5 draft orders with items and status history
        $draftOrders = Order::factory(5)
            ->draft()
            ->create();

        foreach ($draftOrders as $order) {
            // Create 3 items for each order
            OrderItem::factory(3)->create([
                'order_id' => $order->id
            ]);

            // Update order total
            $total = $order->items()->sum('total_price');
            $order->update(['total_amount' => $total]);

            // Create status history
            OrderStatusHistory::factory()->toDraft()->create([
                'order_id' => $order->id,
                'changed_by' => $order->created_by
            ]);
        }

        // Create 3 orders in pending approval status
        $pendingOrders = Order::factory(3)
            ->pendingApproval()
            ->requiresApproval()
            ->create();

        foreach ($pendingOrders as $order) {
            // Create 2 items for each order
            OrderItem::factory(2)->highValue()->create([
                'order_id' => $order->id
            ]);

            // Update order total
            $total = $order->items()->sum('total_price');
            $order->update(['total_amount' => $total]);

            // Create status history
            OrderStatusHistory::factory()->toDraft()->create([
                'order_id' => $order->id,
                'changed_by' => $order->created_by
            ]);

            OrderStatusHistory::factory()->toPendingApproval()->create([
                'order_id' => $order->id,
                'changed_by' => $order->created_by
            ]);
        }

        // Create 2 approved orders
        $approvedOrders = Order::factory(2)
            ->approved()
            ->create();

        foreach ($approvedOrders as $order) {
            // Create 4 items for each order
            OrderItem::factory(4)->create([
                'order_id' => $order->id
            ]);

            // Update order total
            $total = $order->items()->sum('total_price');
            $order->update(['total_amount' => $total]);

            // Create status history
            OrderStatusHistory::factory()->toDraft()->create([
                'order_id' => $order->id,
                'changed_by' => $order->created_by
            ]);

            OrderStatusHistory::factory()->toPendingApproval()->create([
                'order_id' => $order->id,
                'changed_by' => $order->created_by
            ]);

            OrderStatusHistory::factory()->toApproved()->create([
                'order_id' => $order->id,
                'changed_by' => $order->approved_by
            ]);
        }

        // Create 1 rejected order
        $rejectedOrder = Order::factory()
            ->rejected()
            ->create();

        // Create 2 items for the rejected order
        OrderItem::factory(2)->create([
            'order_id' => $rejectedOrder->id
        ]);

        // Update order total
        $total = $rejectedOrder->items()->sum('total_price');
        $rejectedOrder->update(['total_amount' => $total]);

        // Create status history
        OrderStatusHistory::factory()->toDraft()->create([
            'order_id' => $rejectedOrder->id,
            'changed_by' => $rejectedOrder->created_by
        ]);

        OrderStatusHistory::factory()->toPendingApproval()->create([
            'order_id' => $rejectedOrder->id,
            'changed_by' => $rejectedOrder->created_by
        ]);

        OrderStatusHistory::factory()->toRejected()->create([
            'order_id' => $rejectedOrder->id,
            'changed_by' => $rejectedOrder->approved_by
        ]);
    }
}
