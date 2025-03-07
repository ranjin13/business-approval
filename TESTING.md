# Testing Documentation

This document outlines the testing strategy for the Business Approval Workflow API.

## Test Database Setup

The application uses PostgreSQL for testing with a separate database named `business_approval_test`. This approach ensures that tests don't interfere with your production database.

### Setting Up the Test Database

1. Create the test database in PostgreSQL:
   ```sql
   CREATE DATABASE business_approval_test;
   ```

2. Configure your PostgreSQL credentials in `.env.testing`:
   ```
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=business_approval_test
   DB_USERNAME=postgres
   DB_PASSWORD=password
   ```

3. Run the migrations on the test database:
   ```bash
   php artisan migrate:fresh --seed --env=testing
   ```

### Using the Test Scripts

For convenience, we've provided scripts to set up the test database and run tests:

#### Windows (PowerShell)

```powershell
# Run all tests
.\run-tests.ps1

# Run specific tests
.\run-tests.ps1 --filter=OrderServiceTest
```

#### Unix/Linux/Mac (Bash)

```bash
# Make the script executable
chmod +x run-tests.sh

# Run all tests
./run-tests.sh

# Run specific tests
./run-tests.sh --filter=OrderServiceTest
```

### Running Tests Manually

To run all tests:

```bash
php artisan test
```

To run specific test files:

```bash
php artisan test --filter=OrderServiceTest
php artisan test --filter=OrderTest
php artisan test --filter=OrderItemTest
php artisan test --filter=OrderApiTest
```

## Test Coverage

The application includes comprehensive tests for the core business logic, including:

1. **Unit Tests**
   - Order model tests
   - OrderItem model tests
   - OrderService tests

2. **API Tests**
   - Order API endpoint tests

## Test Details

### Unit Tests

#### OrderServiceTest

Tests the core business logic in the OrderService class:

- **test_generate_order_number**: Tests unique order number generation
- **test_create_order_with_validation**: Tests order creation with validation
- **test_create_order_validation_empty_items**: Tests validation for empty items
- **test_submit_order_with_approval_required**: Tests order submission with approval required
- **test_submit_order_without_approval_required**: Tests order submission without approval required
- **test_approve_order**: Tests order approval
- **test_reject_order**: Tests order rejection
- **test_update_order_validation_approved_orders**: Tests validation for updating approved orders
- **test_update_order_with_recalculation**: Tests order update with total recalculation

#### OrderTest

Tests the Order model functionality:

- **test_order_relationships**: Tests order relationships
- **test_requires_approval_method**: Tests the requiresApproval method
- **test_can_be_modified_method**: Tests the canBeModified method
- **test_order_status_transitions**: Tests order status transitions
- **test_soft_delete_functionality**: Tests soft delete functionality

#### OrderItemTest

Tests the OrderItem model functionality:

- **test_order_item_relationship**: Tests order item relationship
- **test_calculate_total_price_method**: Tests the calculateTotalPrice method
- **test_total_price_calculation_on_create**: Tests total price calculation on create
- **test_total_price_recalculation_on_update**: Tests total price recalculation on update
- **test_multiple_order_items_for_an_order**: Tests multiple order items for an order

### API Tests

#### OrderApiTest

Tests the API endpoints:

- **test_get_all_orders**: Tests getting all orders
- **test_get_specific_order**: Tests getting a specific order
- **test_create_order**: Tests creating an order
- **test_create_order_validation**: Tests validation for creating an order
- **test_update_order**: Tests updating an order
- **test_submit_order**: Tests submitting an order
- **test_approve_order**: Tests approving an order
- **test_reject_order**: Tests rejecting an order
- **test_get_order_history**: Tests getting order history

## Test Coverage Summary

The tests cover the following key aspects of the application:

1. **Unique Number Generation**
   - Tests ensure that order numbers are unique and follow the required pattern

2. **Validation Rules**
   - Tests validate that orders must have at least one item
   - Tests validate that approved orders cannot be modified
   - Tests validate input data for creating and updating orders

3. **Calculation Accuracy**
   - Tests verify that order totals are calculated correctly
   - Tests verify that item totals are calculated correctly based on quantity and unit price

4. **Business Rules**
   - Tests verify that orders above $1000 require approval
   - Tests verify the correct status transitions in the approval workflow
   - Tests verify that order history is maintained correctly 