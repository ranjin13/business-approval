<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test order relationships.
     */
    public function test_order_relationships(): void
    {
        // Create an order with items and status history
        $order = Order::factory()->create([
            'created_by' => $this->user->id
        ]);

        // Create order items
        OrderItem::factory(3)->create([
            'order_id' => $order->id
        ]);

        // Create order status history
        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'changed_by' => $this->user->id
        ]);

        // Assert relationships
        $this->assertCount(3, $order->items);
        $this->assertCount(1, $order->statusHistory);
        $this->assertEquals($this->user->id, $order->creator->id);
    }

    /**
     * Test requires approval method.
     */
    public function test_requires_approval_method(): void
    {
        // Create an order with total amount < 1000
        $lowValueOrder = Order::factory()->create([
            'total_amount' => 500.00,
            'created_by' => $this->user->id
        ]);

        // Create an order with total amount >= 1000
        $highValueOrder = Order::factory()->create([
            'total_amount' => 1500.00,
            'created_by' => $this->user->id
        ]);

        // Assert requires approval method
        $this->assertFalse($lowValueOrder->requiresApproval());
        $this->assertTrue($highValueOrder->requiresApproval());
    }

    /**
     * Test can be modified method.
     */
    public function test_can_be_modified_method(): void
    {
        // Create orders with different statuses
        $draftOrder = Order::factory()->draft()->create([
            'created_by' => $this->user->id
        ]);

        $pendingOrder = Order::factory()->pendingApproval()->create([
            'created_by' => $this->user->id
        ]);

        $approvedOrder = Order::factory()->approved()->create([
            'created_by' => $this->user->id,
            'approved_by' => $this->user->id
        ]);

        $rejectedOrder = Order::factory()->rejected()->create([
            'created_by' => $this->user->id,
            'approved_by' => $this->user->id
        ]);

        // Assert can be modified method
        $this->assertTrue($draftOrder->canBeModified());
        $this->assertTrue($pendingOrder->canBeModified());
        $this->assertFalse($approvedOrder->canBeModified());
        $this->assertTrue($rejectedOrder->canBeModified());
    }

    /**
     * Test order status transitions.
     */
    public function test_order_status_transitions(): void
    {
        // Create a draft order
        $order = Order::factory()->draft()->create([
            'created_by' => $this->user->id
        ]);

        // Update status to pending_approval
        $order->status = 'pending_approval';
        $order->save();
        $this->assertEquals('pending_approval', $order->status);

        // Update status to approved
        $order->status = 'approved';
        $order->approved_by = $this->user->id;
        $order->approved_at = now();
        $order->save();
        $this->assertEquals('approved', $order->status);
        $this->assertEquals($this->user->id, $order->approved_by);
        $this->assertNotNull($order->approved_at);

        // Create another order and update status to rejected
        $order2 = Order::factory()->draft()->create([
            'created_by' => $this->user->id
        ]);
        $order2->status = 'rejected';
        $order2->save();
        $this->assertEquals('rejected', $order2->status);
    }

    /**
     * Test soft delete functionality.
     */
    public function test_soft_delete_functionality(): void
    {
        // Create an order
        $order = Order::factory()->create([
            'created_by' => $this->user->id
        ]);

        // Create order items
        OrderItem::factory(2)->create([
            'order_id' => $order->id
        ]);

        // Delete the order
        $order->delete();

        // Assert order is soft deleted
        $this->assertSoftDeleted($order);
        
        // Assert order can be restored
        $order->restore();
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
