# Business Approval Workflow API

A Laravel 12 application that handles a business approval workflow. The system processes orders through validation and a basic approval flow.

## Business Requirements

1. **Order Processing**
   - Auto-generate unique order numbers following pattern
   - Create orders with multiple items
   - Calculate order totals
   - Basic 2-steps approval workflow
   - Track order status changes

2. **Business Rules**
   - Order numbers must be unique and sequential
   - Orders above $1000 require approval
   - Orders must have at least one item
   - Approved orders cannot be modified
   - Basic order history must be maintained

3. **Required Functionality**
   - Auto-generation of order numbers
   - Order creation and validation
   - Total calculation
   - Approval processing
   - Status tracking

## Technical Implementation

### Database Structure

1. **Orders Table**
   - id (primary key)
   - order_number (unique)
   - status (enum: draft, pending_approval, approved, rejected)
   - total_amount
   - notes
   - created_by (foreign key to users)
   - approved_by (foreign key to users, nullable)
   - approved_at (timestamp, nullable)
   - created_at, updated_at, deleted_at

2. **Order Items Table**
   - id (primary key)
   - order_id (foreign key to orders)
   - product_name
   - description (nullable)
   - quantity
   - unit_price
   - total_price
   - created_at, updated_at

3. **Order Status History Table**
   - id (primary key)
   - order_id (foreign key to orders)
   - from_status (enum, nullable)
   - to_status (enum)
   - changed_by (foreign key to users)
   - comments (nullable)
   - created_at, updated_at

### API Endpoints

#### Orders

- **GET /api/orders** - Get all orders
- **GET /api/orders/{id}** - Get a specific order with its items
- **POST /api/orders** - Create a new order
- **PUT /api/orders/{id}** - Update an existing order
- **POST /api/orders/{id}/submit** - Submit an order for approval
- **POST /api/orders/{id}/approve** - Approve an order
- **POST /api/orders/{id}/reject** - Reject an order
- **GET /api/orders/{id}/history** - Get order status history

## Prerequisites
- PHP 8.2+
- PostgreSQL 12+

## Installation

1. Clone the repository
   ```
   git clone https://github.com/ranjin13/business-approval.git
   cd business-approval
   ```
2. Install dependencies:
   ```
   composer install
   ```
3. Copy the `.env.example` file to `.env` and `.env.testing` configure your database
4. Generate application key:
   ```
   php artisan key:generate
   ```
5. Run migrations:
   ```
   php artisan migrate
   ```
6. (Optional) Seed the database:
   ```
   php artisan db:seed
   ```
7. Start the development server:
   ```
   php artisan serve
   ```
## Database
- Main Database
   ```
   database: astudio_assessment
   user: postgres
   password: password
   ```
- Testing Database
astudio_assessment_test
   ```
   database: astudio_assessment_test
   user: postgres
   password: password
   ```

## API Documentation
- https://documenter.getpostman.com/view/8163430/2sAYdoDSYj

## Factories and Testing

The application includes factories for all models to help with testing and seeding data:

### Order Factory

```php
// Create a basic order
$order = Order::factory()->create();

// Create an order in draft status
$draftOrder = Order::factory()->draft()->create();

// Create an order in pending approval status
$pendingOrder = Order::factory()->pendingApproval()->create();

// Create an order that requires approval (total amount >= 1000)
$highValueOrder = Order::factory()->requiresApproval()->create();

// Create an approved order
$approvedOrder = Order::factory()->approved()->create();

// Create a rejected order
$rejectedOrder = Order::factory()->rejected()->create();
```

### OrderItem Factory

```php
// Create a basic order item
$orderItem = OrderItem::factory()->create();

// Create a high-value item
$highValueItem = OrderItem::factory()->highValue()->create();

// Create a low-value item
$lowValueItem = OrderItem::factory()->lowValue()->create();

// Create an order with 3 items
$order = Order::factory()
    ->has(OrderItem::factory()->count(3))
    ->create();
```

### OrderStatusHistory Factory

```php
// Create a basic status history record
$statusHistory = OrderStatusHistory::factory()->create();

// Create a status change to draft
$draftStatus = OrderStatusHistory::factory()->toDraft()->create();

// Create a status change to pending approval
$pendingStatus = OrderStatusHistory::factory()->toPendingApproval()->create();

// Create a status change to approved
$approvedStatus = OrderStatusHistory::factory()->toApproved()->create();

// Create a status change to rejected
$rejectedStatus = OrderStatusHistory::factory()->toRejected()->create();
```

## API Request Examples

### Create Order

```json
POST /api/orders
{
  "notes": "Sample order",
  "user_id": 1,
  "items": [
    {
      "product_name": "Product 1",
      "description": "Description for product 1",
      "quantity": 2,
      "unit_price": 100.00
    },
    {
      "product_name": "Product 2",
      "description": "Description for product 2",
      "quantity": 1,
      "unit_price": 50.00
    }
  ]
}
```

### Submit Order

```json
POST /api/orders/1/submit
{
  "user_id": 1
}
```

### Approve Order

```json
POST /api/orders/1/approve
{
  "user_id": 1,
  "comments": "Approved by manager"
}
```

### Reject Order

```json
POST /api/orders/1/reject
{
  "user_id": 1,
  "comments": "Budget exceeded"
}
```
