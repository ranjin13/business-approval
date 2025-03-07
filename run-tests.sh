#!/bin/bash

# Check if the PostgreSQL test database exists
DB_EXISTS=$(psql -U postgres -t -c "SELECT 1 FROM pg_database WHERE datname='business_approval_test'" | grep 1)

if [ -z "$DB_EXISTS" ]; then
    echo "Test database does not exist. Creating it now..."
    psql -U postgres -c "CREATE DATABASE business_approval_test;"
    echo "Test database created."
fi

# Run migrations on the test database
echo "Running migrations on the test database..."
php artisan migrate:fresh --seed --env=testing

# Run the tests
echo "Running tests with PostgreSQL database..."
php artisan test "$@"

echo "Tests completed!" 