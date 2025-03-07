# Check if the PostgreSQL test database exists
$dbExists = $null
try {
    $dbExists = psql -U postgres -c "SELECT 1 FROM pg_database WHERE datname='business_approval_test'" | Select-String -Pattern "1 row"
} catch {
    Write-Host "Error checking if database exists. Make sure PostgreSQL is installed and running."
    exit 1
}

if (-not $dbExists) {
    Write-Host "Test database does not exist. Creating it now..."
    psql -U postgres -c "CREATE DATABASE business_approval_test;"
    Write-Host "Test database created."
}

# Run migrations on the test database
Write-Host "Running migrations on the test database..."
php artisan migrate:fresh --seed --env=testing

# Run the tests
Write-Host "Running tests with PostgreSQL database..."
php artisan test $args

Write-Host "Tests completed!" 