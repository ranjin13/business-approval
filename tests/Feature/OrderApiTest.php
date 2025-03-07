<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test get all orders endpoint.
     */
    public function test_get_all_orders(): void
    {
        // Create some orders
        $orders = Order::factory(3)->create([
            'created_by' => $this->user->id
        ]);

        // Create items for each order
        foreach ($orders as $order) {
            OrderItem::factory(2)->create([
                'order_id' => $order->id
            ]);
        }

        // Call the API endpoint
        $response = $this->getJson('/api/orders');

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'order_number',
                        'status',
                        'total_amount',
                        'notes',
                        'created_by',
                        'approved_by',
                        'approved_at',
                        'created_at',
                        'updated_at',
                        'deleted_at',
                        'items'
                    ]
                ]
            ])
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test get specific order endpoint.
     */
    public function test_get_specific_order(): void
    {
        // Create an order with items
        $order = Order::factory()->create([
            'created_by' => $this->user->id
        ]);

        // Create items for the order
        OrderItem::factory(2)->create([
            'order_id' => $order->id
        ]);

        // Call the API endpoint
        $response = $this->getJson("/api/orders/{$order->id}");

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'total_amount',
                    'notes',
                    'created_by',
                    'approved_by',
                    'approved_at',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                    'items',
                    'status_history',
                    'creator',
                    'approver'
                ]
            ])
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    /**
     * Test create order endpoint.
     */
    public function test_create_order(): void
    {
        // Prepare request data
        $data = [
            'notes' => 'Test order from API',
            'user_id' => $this->user->id,
            'items' => [
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
            ]
        ];

        // Call the API endpoint
        $response = $this->postJson('/api/orders', $data);

        // Assert response
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'total_amount',
                    'notes',
                    'created_by',
                    'items'
                ]
            ])
            ->assertJsonPath('data.notes', 'Test order from API')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(2, 'data.items');

        // Assert order was created in database
        $this->assertDatabaseHas('orders', [
            'notes' => 'Test order from API',
            'status' => 'draft',
            'created_by' => $this->user->id
        ]);
    }

    /**
     * Test create order validation.
     */
    public function test_create_order_validation(): void
    {
        // Prepare invalid request data (no items)
        $data = [
            'notes' => 'Test order from API',
            'user_id' => $this->user->id,
            'items' => []
        ];

        // Call the API endpoint
        $response = $this->postJson('/api/orders', $data);

        // Assert validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // Prepare invalid request data (invalid item data)
        $data = [
            'notes' => 'Test order from API',
            'user_id' => $this->user->id,
            'items' => [
                [
                    'product_name' => '', // Empty product name
                    'quantity' => 0, // Invalid quantity
                    'unit_price' => -10 // Invalid unit price
                ]
            ]
        ];

        // Call the API endpoint
        $response = $this->postJson('/api/orders', $data);

        // Assert validation errors
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.product_name',
                'items.0.quantity',
                'items.0.unit_price'
            ]);
    }

    /**
     * Test update order endpoint.
     */
    public function test_update_order(): void
    {
        // Create an order with items
        $order = Order::factory()->draft()->create([
            'notes' => 'Original notes',
            'created_by' => $this->user->id
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Original Product',
            'quantity' => 1,
            'unit_price' => 100.00,
            'total_price' => 100.00
        ]);

        // Prepare update data
        $data = [
            'notes' => 'Updated notes',
            'user_id' => $this->user->id,
            'items' => [
                [
                    'product_name' => 'Updated Product 1',
                    'description' => 'Updated Description 1',
                    'quantity' => 3,
                    'unit_price' => 100.00
                ],
                [
                    'product_name' => 'New Product 2',
                    'description' => 'New Description 2',
                    'quantity' => 1,
                    'unit_price' => 50.00
                ]
            ]
        ];

        // Call the API endpoint
        $response = $this->putJson("/api/orders/{$order->id}", $data);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'total_amount',
                    'notes',
                    'items'
                ]
            ])
            ->assertJsonPath('data.notes', 'Updated notes')
            ->assertJsonCount(2, 'data.items');

        // Assert order was updated in database
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'notes' => 'Updated notes'
        ]);

        // Assert old items were replaced
        $this->assertDatabaseMissing('order_items', [
            'order_id' => $order->id,
            'product_name' => 'Original Product'
        ]);

        // Assert new items were created
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_name' => 'Updated Product 1',
            'quantity' => 3,
            'unit_price' => 100.00,
            'total_price' => 300.00
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_name' => 'New Product 2',
            'quantity' => 1,
            'unit_price' => 50.00,
            'total_price' => 50.00
        ]);
    }

    /**
     * Test submit order endpoint.
     */
    public function test_submit_order(): void
    {
        // Create a draft order
        $order = Order::factory()->draft()->create([
            'created_by' => $this->user->id
        ]);

        // Add items to make total >= 1000 (requires approval)
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 10,
            'unit_price' => 150.00,
            'total_price' => 1500.00
        ]);

        $order->total_amount = 1500.00;
        $order->save();

        // Prepare request data
        $data = [
            'user_id' => $this->user->id
        ];

        // Call the API endpoint
        $response = $this->postJson("/api/orders/{$order->id}/submit", $data);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'status',
                    'total_amount'
                ]
            ])
            ->assertJsonPath('data.status', 'pending_approval');

        // Assert order status was updated in database
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending_approval'
        ]);

        // Assert status history was created
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => 'draft',
            'to_status' => 'pending_approval',
            'changed_by' => $this->user->id
        ]);
    }

    /**
     * Test approve order endpoint.
     */
    public function test_approve_order(): void
    {
        // Create a pending approval order
        $order = Order::factory()->pendingApproval()->create([
            'created_by' => $this->user->id
        ]);

        // Prepare request data
        $data = [
            'user_id' => $this->user->id,
            'comments' => 'Approved by test'
        ];

        // Call the API endpoint
        $response = $this->postJson("/api/orders/{$order->id}/approve", $data);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'status',
                    'approved_by',
                    'approved_at'
                ]
            ])
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $this->user->id);

        // Assert order status was updated in database
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'approved',
            'approved_by' => $this->user->id
        ]);

        // Assert status history was created
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => 'pending_approval',
            'to_status' => 'approved',
            'changed_by' => $this->user->id,
            'comments' => 'Approved by test'
        ]);
    }

    /**
     * Test reject order endpoint.
     */
    public function test_reject_order(): void
    {
        // Create a pending approval order
        $order = Order::factory()->pendingApproval()->create([
            'created_by' => $this->user->id
        ]);

        // Prepare request data
        $data = [
            'user_id' => $this->user->id,
            'comments' => 'Rejected by test'
        ];

        // Call the API endpoint
        $response = $this->postJson("/api/orders/{$order->id}/reject", $data);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'status'
                ]
            ])
            ->assertJsonPath('data.status', 'rejected');

        // Assert order status was updated in database
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'rejected'
        ]);

        // Assert status history was created
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => 'pending_approval',
            'to_status' => 'rejected',
            'changed_by' => $this->user->id,
            'comments' => 'Rejected by test'
        ]);
    }

    /**
     * Test get order history endpoint.
     */
    public function test_get_order_history(): void
    {
        // Create an order
        $order = Order::factory()->create([
            'created_by' => $this->user->id
        ]);

        // Create status history records
        $history1 = [
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => 'draft',
            'changed_by' => $this->user->id,
            'comments' => 'Order created'
        ];

        $history2 = [
            'order_id' => $order->id,
            'from_status' => 'draft',
            'to_status' => 'pending_approval',
            'changed_by' => $this->user->id,
            'comments' => 'Order submitted'
        ];

        $order->statusHistory()->create($history1);
        $order->statusHistory()->create($history2);

        // Call the API endpoint
        $response = $this->getJson("/api/orders/{$order->id}/history");

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'order_id',
                        'from_status',
                        'to_status',
                        'changed_by',
                        'comments',
                        'created_at',
                        'updated_at',
                        'changed_by' => [
                            'id',
                            'name',
                            'email'
                        ]
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data');
    }
}
