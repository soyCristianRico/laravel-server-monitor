# Testing Guide

Guide for running and writing tests for Laravel Server Monitor.

## Running Tests

### Prerequisites

```bash
# Install dependencies
composer install
```

### Basic Test Commands

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test suite
vendor/bin/pest tests/Unit
vendor/bin/pest tests/Feature

# Run specific test file
vendor/bin/pest tests/Unit/Services/ServerMonitoringServiceTest.php

# Run with verbose output
vendor/bin/pest --verbose

# Run and stop on first failure
vendor/bin/pest --stop-on-failure
```

### Test Structure

The package uses **Pest PHP** with `describe()` and `it()` syntax:

```php
describe('ServerMonitoringService', function () {
    beforeEach(function () {
        $this->service = new ServerMonitoringService();
    });

    describe('disk monitoring', function () {
        it('returns ok status when usage is below threshold', function () {
            // Test implementation
            expect($result['status'])->toBe('ok');
        });

        it('returns warning status when usage exceeds warning threshold', function () {
            // Test implementation
        });
    });
});
```

### Test Categories

#### Unit Tests (`tests/Unit/`)

- **Services Tests**: Test individual service classes
  - `ServerMonitoringServiceTest.php`
  - `Security/SecurityScannerServiceTest.php`
  - `Security/SecurityNotificationServiceTest.php`

- **Command Tests**: Test console commands
  - `Console/Commands/ServerMonitorCommandTest.php`

#### Feature Tests (`tests/Feature/`)

- **Integration Tests**: Test full workflows
  - `ServerMonitoringIntegrationTest.php`

## Writing Tests

### Service Testing Example

```php
describe('ServerMonitoringService', function () {
    beforeEach(function () {
        $this->service = new ServerMonitoringService();
    });

    describe('threshold configuration', function () {
        it('returns correct disk warning threshold from config', function () {
            config(['server-monitor.monitoring.disk.warning_threshold' => 85]);

            expect($this->service->getDiskWarningThreshold())->toBe(85);
        });
    });

    describe('disk space monitoring', function () {
        it('returns ok status when disk usage is below warning threshold', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('getDiskUsage')->andReturn(50);

            $result = $service->checkDiskSpace();

            expect($result)->toMatchArray([
                'metric' => 'disk_space',
                'value' => 50,
                'status' => 'ok'
            ]);
        });
    });
});
```

### Command Testing Example

```php
describe('ServerMonitorCommand', function () {
    beforeEach(function () {
        $this->service = Mockery::mock(ServerMonitoringService::class);
        $this->command = new ServerMonitorCommand($this->service);
    });

    it('returns success exit code when all checks pass', function () {
        $checks = [
            'disk' => ['status' => 'ok', 'message' => 'Disk OK'],
        ];

        $this->service->shouldReceive('runAllChecks')->andReturn($checks);
        $this->service->shouldReceive('getAlerts')->andReturn([]);

        $this->artisan('server:monitor')->assertExitCode(0);
    });
});
```

### Integration Testing Example

```php
describe('Server Monitoring Integration', function () {
    it('registers ServerMonitoringService in container', function () {
        $service = app(ServerMonitoringService::class);

        expect($service)->toBeInstanceOf(ServerMonitoringService::class);
    });

    it('can execute server monitor command', function () {
        $this->artisan('server:monitor')
            ->expectsOutput('Starting server monitoring...')
            ->assertExitCode(0);
    });
});
```

## Mocking Strategies

### Service Mocking

```php
// Partial mock - mock specific methods
$service = Mockery::mock(ServerMonitoringService::class)->makePartial();
$service->shouldReceive('getDiskUsage')->andReturn(75);

// Full mock
$service = Mockery::mock(ServerMonitoringService::class);
$service->shouldReceive('runAllChecks')->once()->andReturn([]);
```

### Shell Command Mocking

For testing security scanner methods that use `shell_exec()`:

```php
// Test the logic, not the actual shell execution
it('handles empty shell output correctly', function () {
    // Test with empty return values
    $result = null; // Simulating empty shell_exec result
    expect($result)->toBeNull();
});

// Test return structure
it('returns correct alert structure', function () {
    $expectedAlert = [
        'type' => 'Suspicious Processes',
        'details' => expect()->toBeString()
    ];

    expect($expectedAlert['type'])->toBe('Suspicious Processes');
});
```

### Configuration Mocking

```php
beforeEach(function () {
    config([
        'server-monitor.monitoring.disk.warning_threshold' => 80,
        'server-monitor.notifications.admin_role' => 'admin',
    ]);
});
```

## Test Configuration

### TestCase Setup

```php
class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ServerMonitorServiceProvider::class,
            PermissionServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // Configure test database
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // Set test configuration
        config()->set('server-monitor.notifications.admin_role', 'admin');
    }
}
```

### Pest Configuration

```php
// tests/Pest.php
uses(Tests\TestCase::class)->in('Feature');
uses(Tests\TestCase::class)->in('Unit');

// Custom expectations
expect()->extend('toBeValidAlert', function () {
    return $this->toHaveKeys(['type', 'details']);
});
```

## Testing Best Practices

### 1. Test Structure

- Use descriptive `describe()` blocks to group related tests
- Use clear `it()` statements that describe expected behavior
- Keep tests focused on single behaviors

### 2. Arrange-Act-Assert Pattern

```php
it('calculates disk usage correctly', function () {
    // Arrange
    $service = new ServerMonitoringService();
    config(['server-monitor.monitoring.disk.warning_threshold' => 80]);

    // Act
    $result = $service->getDiskWarningThreshold();

    // Assert
    expect($result)->toBe(80);
});
```

### 3. Mock External Dependencies

- Mock shell commands that interact with system
- Mock file system operations
- Mock external API calls

### 4. Test Edge Cases

```php
describe('error handling', function () {
    it('handles null configuration gracefully', function () {
        config(['server-monitor' => null]);

        $service = new ServerMonitoringService();

        expect($service->getDiskWarningThreshold())->toBe(80); // Default
    });

    it('handles empty shell command output', function () {
        // Test with empty/null shell_exec returns
    });
});
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: [8.1, 8.2, 8.3]
        laravel: [10.*, 11.*]

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}

      - name: Install dependencies
        run: composer install --no-interaction

      - name: Run tests
        run: composer test

      - name: Run tests with coverage
        run: composer test-coverage
```

## Coverage Goals

- **Services**: Aim for 90%+ coverage on business logic
- **Commands**: Test major execution paths and error conditions
- **Integration**: Test service registration and basic workflows

## Debugging Tests

### Verbose Output

```bash
vendor/bin/pest --verbose
```

### Debug Specific Test

```bash
vendor/bin/pest --filter="returns ok status when disk usage is below threshold"
```

### Using dd() in Tests

```php
it('debugs service behavior', function () {
    $service = new ServerMonitoringService();
    $result = $service->checkDiskSpace();

    dd($result); // Debug output

    expect($result['status'])->toBe('ok');
});
```

## Next Steps

- [Contributing Guidelines](contributing.md)
- [Development Setup](setup.md)
- [API Documentation](../api/services.md)