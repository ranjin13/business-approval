<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->order = Order::factory()->create([
            'created_by' => $this->user->id
        ]);
    }

    /**
     * Test order item relationship.
     */
    public function test_order_item_relationship(): void
    {
        // Create an order item
        $orderItem = OrderItem::factory()->create([
            'order_id' => $this->order->id
        ]);

        // Assert relationship
        $this->assertEquals($this->order->id, $orderItem->order->id);
    }

    /**
     * Test calculate total price method.
     */
    public function test_calculate_total_price_method(): void
    {
        // Create an order item
        $orderItem = new OrderItem();
        $orderItem->order_id = $this->order->id;
        $orderItem->product_name = 'Test Product';
        $orderItem->quantity = 3;
        $orderItem->unit_price = 100.00;
        
        // Calculate total price
        $orderItem->calculateTotalPrice();
        
        // Assert total price is calculated correctly
        $this->assertEquals(300.00, $orderItem->total_price);
    }

    /**
     * Test total price calculation on create.
     */
    public function test_total_price_calculation_on_create(): void
    {
        // Create an order item without setting total_price
        $orderItem = OrderItem::factory()->make([
            'order_id' => $this->order->id,
            'quantity' => 2,
            'unit_price' => 150.00,
            'total_price' => null
        ]);
        
        $orderItem->save();
        
        // Assert total price is calculated automatically
        $this->assertEquals(300.00, $orderItem->total_price);
    }

    /**
     * Test total price recalculation on update.
     */
    public function test_total_price_recalculation_on_update(): void
    {
        // Create an order item
        $orderItem = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total_price' => 200.00
        ]);
        
        // Update quantity
        $orderItem->quantity = 3;
        $orderItem->save();
        
        // Assert total price is recalculated
        $this->assertEquals(300.00, $orderItem->total_price);
        
        // Update unit price
        $orderItem->unit_price = 150.00;
        $orderItem->save();
        
        // Assert total price is recalculated
        $this->assertEquals(450.00, $orderItem->total_price);
    }

    /**
     * Test multiple order items for an order.
     */
    public function test_multiple_order_items_for_an_order(): void
    {
        // Create multiple order items
        $item1 = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total_price' => 200.00
        ]);
        
        $item2 = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'quantity' => 1,
            'unit_price' => 50.00,
            'total_price' => 50.00
        ]);
        
        $item3 = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'quantity' => 3,
            'unit_price' => 75.00,
            'total_price' => 225.00
        ]);
        
        // Assert order has multiple items
        $this->assertCount(3, $this->order->items);
        
        // Assert total amount calculation
        $totalAmount = $this->order->items->sum('total_price');
        $this->assertEquals(475.00, $totalAmount);
    }
}
