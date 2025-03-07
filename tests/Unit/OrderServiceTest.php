<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrderService $orderService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
        $this->user = User::factory()->create();
    }

    /**
     * Test order number generation.
     */
    public function test_generate_order_number(): void
    {
        // Create an order to ensure the next order number is different
        $order = Order::factory()->create([
            'order_number' => 'ORD-' . date('Ymd') . '-0001',
            'created_by' => $this->user->id
        ]);
        
        // Generate a new order number
        $orderNumber = $this->orderService->generateOrderNumber();
        
        // Assert order number follows the pattern ORD-YYYYMMDD-XXXX
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $orderNumber);
        
        // Assert order number is different from the existing one
        $this->assertNotEquals($order->order_number, $orderNumber);
    }

    /**
     * Test order creation with validation.
     */
    public function test_create_order_with_validation(): void
    {
        // Test creating an order with items
        $orderData = ['notes' => 'Test order'];
        $items = [
            [
                'product_name' => 'Product 1',
                'description' => 'Description 1',
                'quantity' => 2,
                'unit_price' => 100.00
            ],
            [
                'product_name' => 'Product 2',
                'description' => 'Description 2',
                'quantity' => 1,
                'unit_price' => 50.00
            ]
        ];

        $order = $this->orderService->createOrder($orderData, $items, $this->user->id);

        // Assert order was created
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals('draft', $order->status);
        $this->assertEquals($this->user->id, $order->created_by);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order->order_number);
        
        // Assert items were created
        $this->assertCount(2, $order->items);
        
        // Assert total amount was calculated correctly
        $this->assertEquals(250.00, $order->total_amount);
    }

    /**
     * Test order creation validation for empty items.
     */
    public function test_create_order_validation_empty_items(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order must have at least one item');

        $orderData = ['notes' => 'Test order'];
        $items = [];

        $this->orderService->createOrder($orderData, $items, $this->user->id);
    }

    /**
     * Test order submission with approval required.
     */
    public function test_submit_order_with_approval_required(): void
    {
        // Create an order with total amount >= 1000
        $order = Order::factory()->draft()->create([
            'total_amount' => 1500.00,
            'created_by' => $this->user->id
        ]);

        // Add items to the order
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 1,
            'unit_price' => 1500.00,
            'total_price' => 1500.00
        ]);

        // Submit the order
        $submittedOrder = $this->orderService->submitOrder($order, $this->user->id);

        // Assert order status is pending_approval
        $this->assertEquals('pending_approval', $submittedOrder->status);
        $this->assertNull($submittedOrder->approved_by);
        $this->assertNull($submittedOrder->approved_at);
    }

    /**
     * Test order submission without approval required.
     */
    public function test_submit_order_without_approval_required(): void
    {
        // Create an order with total amount < 1000
        $order = Order::factory()->draft()->create([
            'total_amount' => 500.00,
            'created_by' => $this->user->id
        ]);

        // Add items to the order
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 1,
            'unit_price' => 500.00,
            'total_price' => 500.00
        ]);

        // Submit the order
        $submittedOrder = $this->orderService->submitOrder($order, $this->user->id);

        // Assert order status is approved
        $this->assertEquals('approved', $submittedOrder->status);
        $this->assertEquals($this->user->id, $submittedOrder->approved_by);
        $this->assertNotNull($submittedOrder->approved_at);
    }

    /**
     * Test order approval.
     */
    public function test_approve_order(): void
    {
        // Create an order in pending_approval status
        $order = Order::factory()->pendingApproval()->create([
            'created_by' => $this->user->id
        ]);

        // Approve the order
        $approvedOrder = $this->orderService->approveOrder($order, $this->user->id, 'Approved by test');

        // Assert order status is approved
        $this->assertEquals('approved', $approvedOrder->status);
        $this->assertEquals($this->user->id, $approvedOrder->approved_by);
        $this->assertNotNull($approvedOrder->approved_at);
        
        // Assert status history was created
        $this->assertCount(1, $approvedOrder->statusHistory);
        $latestHistory = $approvedOrder->statusHistory->first();
        $this->assertEquals('pending_approval', $latestHistory->from_status);
        $this->assertEquals('approved', $latestHistory->to_status);
        $this->assertEquals('Approved by test', $latestHistory->comments);
    }

    /**
     * Test order rejection.
     */
    public function test_reject_order(): void
    {
        // Create an order in pending_approval status
        $order = Order::factory()->pendingApproval()->create([
            'created_by' => $this->user->id
        ]);

        // Reject the order
        $rejectedOrder = $this->orderService->rejectOrder($order, $this->user->id, 'Rejected by test');

        // Assert order status is rejected
        $this->assertEquals('rejected', $rejectedOrder->status);
        
        // Assert status history was created
        $this->assertCount(1, $rejectedOrder->statusHistory);
        $latestHistory = $rejectedOrder->statusHistory->first();
        $this->assertEquals('pending_approval', $latestHistory->from_status);
        $this->assertEquals('rejected', $latestHistory->to_status);
        $this->assertEquals('Rejected by test', $latestHistory->comments);
    }

    /**
     * Test order update validation for approved orders.
     */
    public function test_update_order_validation_approved_orders(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Approved orders cannot be modified');

        // Create an approved order
        $order = Order::factory()->approved()->create([
            'created_by' => $this->user->id,
            'approved_by' => $this->user->id
        ]);

        $orderData = ['notes' => 'Updated notes'];
        $items = [
            [
                'product_name' => 'Updated Product',
                'description' => 'Updated Description',
                'quantity' => 1,
                'unit_price' => 100.00
            ]
        ];

        $this->orderService->updateOrder($order, $orderData, $items, $this->user->id);
    }

    /**
     * Test order update with recalculation.
     */
    public function test_update_order_with_recalculation(): void
    {
        // Create a draft order
        $order = Order::factory()->draft()->create([
            'total_amount' => 200.00,
            'created_by' => $this->user->id
        ]);

        // Add initial items
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total_price' => 200.00
        ]);

        // Update the order with new items
        $orderData = ['notes' => 'Updated notes'];
        $items = [
            [
                'product_name' => 'Updated Product 1',
                'description' => 'Updated Description 1',
                'quantity' => 3,
                'unit_price' => 100.00
            ],
            [
                'product_name' => 'Updated Product 2',
                'description' => 'Updated Description 2',
                'quantity' => 1,
                'unit_price' => 50.00
            ]
        ];

        $updatedOrder = $this->orderService->updateOrder($order, $orderData, $items, $this->user->id);

        // Assert order was updated
        $this->assertEquals('Updated notes', $updatedOrder->notes);
        
        // Assert items were updated
        $this->assertCount(2, $updatedOrder->items);
        
        // Assert total amount was recalculated correctly
        $this->assertEquals(350.00, $updatedOrder->total_amount);
    }
}
