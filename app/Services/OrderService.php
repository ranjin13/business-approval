<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderService
{
    /**
     * Generate a unique order number.
     *
     * @return string
     */
    public function generateOrderNumber(): string
    {
        $latestOrder = Order::latest()->first();
        $nextId = $latestOrder ? $latestOrder->id + 1 : 1;
        
        // Format: ORD-YYYYMMDD-XXXX where XXXX is a sequential number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
        
        // Ensure uniqueness by checking if the order number already exists
        $count = 0;
        $originalOrderNumber = $orderNumber;
        while (Order::where('order_number', $orderNumber)->exists() && $count < 100) {
            $nextId++;
            $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            $count++;
        }
        
        // If we've tried 100 times and still have a duplicate, add a random suffix
        if ($count >= 100) {
            $orderNumber = $originalOrderNumber . '-' . uniqid();
        }
        
        return $orderNumber;
    }

    /**
     * Create a new order with items.
     *
     * @param array $orderData
     * @param array $items
     * @param int $userId
     * @return Order
     * @throws Exception
     */
    public function createOrder(array $orderData, array $items, int $userId): Order
    {
        if (empty($items)) {
            throw new Exception('Order must have at least one item');
        }

        try {
            return DB::transaction(function () use ($orderData, $items, $userId) {
                // Create order
                $order = new Order();
                $order->order_number = $this->generateOrderNumber();
                $order->status = 'draft';
                $order->notes = $orderData['notes'] ?? null;
                $order->created_by = $userId;
                $order->save();

                // Add items
                $totalAmount = 0;
                foreach ($items as $itemData) {
                    $item = new OrderItem();
                    $item->product_name = $itemData['product_name'];
                    $item->description = $itemData['description'] ?? null;
                    $item->quantity = $itemData['quantity'];
                    $item->unit_price = $itemData['unit_price'];
                    $item->calculateTotalPrice();
                    
                    $order->items()->save($item);
                    $totalAmount += $item->total_price;
                }

                // Update order total
                $order->total_amount = $totalAmount;
                $order->save();

                // Add status history
                $this->addStatusHistory($order, null, 'draft', $userId);

                return $order;
            });
        } catch (Exception $e) {
            Log::error('Failed to create order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Submit an order for approval.
     *
     * @param Order $order
     * @param int $userId
     * @return Order
     * @throws Exception
     */
    public function submitOrder(Order $order, int $userId): Order
    {
        if ($order->status !== 'draft') {
            throw new Exception('Only draft orders can be submitted');
        }

        if (count($order->items) === 0) {
            throw new Exception('Order must have at least one item');
        }

        try {
            return DB::transaction(function () use ($order, $userId) {
                $fromStatus = $order->status;
                
                // Determine if approval is needed
                if ($order->requiresApproval()) {
                    $order->status = 'pending_approval';
                } else {
                    $order->status = 'approved';
                    $order->approved_by = $userId;
                    $order->approved_at = now();
                }
                
                $order->save();

                // Add status history
                $this->addStatusHistory($order, $fromStatus, $order->status, $userId);

                return $order;
            });
        } catch (Exception $e) {
            Log::error('Failed to submit order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Approve an order.
     *
     * @param Order $order
     * @param int $userId
     * @param string|null $comments
     * @return Order
     * @throws Exception
     */
    public function approveOrder(Order $order, int $userId, ?string $comments = null): Order
    {
        if ($order->status !== 'pending_approval') {
            throw new Exception('Only pending orders can be approved');
        }

        try {
            return DB::transaction(function () use ($order, $userId, $comments) {
                $fromStatus = $order->status;
                $order->status = 'approved';
                $order->approved_by = $userId;
                $order->approved_at = now();
                $order->save();

                // Add status history
                $this->addStatusHistory($order, $fromStatus, 'approved', $userId, $comments);

                return $order;
            });
        } catch (Exception $e) {
            Log::error('Failed to approve order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reject an order.
     *
     * @param Order $order
     * @param int $userId
     * @param string|null $comments
     * @return Order
     * @throws Exception
     */
    public function rejectOrder(Order $order, int $userId, ?string $comments = null): Order
    {
        if ($order->status !== 'pending_approval') {
            throw new Exception('Only pending orders can be rejected');
        }

        try {
            return DB::transaction(function () use ($order, $userId, $comments) {
                $fromStatus = $order->status;
                $order->status = 'rejected';
                $order->save();

                // Add status history
                $this->addStatusHistory($order, $fromStatus, 'rejected', $userId, $comments);

                return $order;
            });
        } catch (Exception $e) {
            Log::error('Failed to reject order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an order.
     *
     * @param Order $order
     * @param array $orderData
     * @param array $items
     * @param int $userId
     * @return Order
     * @throws Exception
     */
    public function updateOrder(Order $order, array $orderData, array $items, int $userId): Order
    {
        if (!$order->canBeModified()) {
            throw new Exception('Approved orders cannot be modified');
        }

        if (empty($items)) {
            throw new Exception('Order must have at least one item');
        }

        try {
            return DB::transaction(function () use ($order, $orderData, $items, $userId) {
                // Update order
                $order->notes = $orderData['notes'] ?? $order->notes;
                
                // Delete existing items
                $order->items()->delete();
                
                // Add new items
                $totalAmount = 0;
                foreach ($items as $itemData) {
                    $item = new OrderItem();
                    $item->product_name = $itemData['product_name'];
                    $item->description = $itemData['description'] ?? null;
                    $item->quantity = $itemData['quantity'];
                    $item->unit_price = $itemData['unit_price'];
                    $item->calculateTotalPrice();
                    
                    $order->items()->save($item);
                    $totalAmount += $item->total_price;
                }

                // Update order total
                $order->total_amount = $totalAmount;
                $order->save();

                return $order;
            });
        } catch (Exception $e) {
            Log::error('Failed to update order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add a status history record.
     *
     * @param Order $order
     * @param string|null $fromStatus
     * @param string $toStatus
     * @param int $userId
     * @param string|null $comments
     * @return OrderStatusHistory
     */
    private function addStatusHistory(Order $order, ?string $fromStatus, string $toStatus, int $userId, ?string $comments = null): OrderStatusHistory
    {
        $history = new OrderStatusHistory();
        $history->order_id = $order->id;
        $history->from_status = $fromStatus;
        $history->to_status = $toStatus;
        $history->changed_by = $userId;
        $history->comments = $comments;
        $history->save();

        return $history;
    }
} 