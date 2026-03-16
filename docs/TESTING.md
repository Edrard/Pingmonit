# Testing

PingMonit includes comprehensive test coverage using Pest.

## Running Tests

### Run all tests
```bash
vendor/bin/pest
```

### Run specific test suite
```bash
# Unit tests only
vendor/bin/pest tests/Unit

# Feature tests only
vendor/bin/pest tests/Feature

# Specific test file
vendor/bin/pest tests/Unit/UpsMonitorTest.php
```

### Run with coverage
```bash
vendor/bin/pest --coverage
```

### Run specific test
```bash
vendor/bin/pest --filter="UPS charge trend detection works correctly"
```

## Test Structure

### Unit Tests (`tests/Unit/`)
- **UpsMonitorTest.php** - UPS monitoring, trend detection, notifications
- **PingMonitorTest.php** - Host monitoring, status changes, notifications
- **NotifierTest.php** - Email and Telegram notification adapters
- **CliRunnerTest.php** - CLI runner, configuration handling
- **StateManagementTest.php** - State persistence and retrieval

### Feature Tests (`tests/Feature/`)
- **ParallelProcessingTest.php** - Parallel host checking, shared state

## Test Coverage

### Core Functionality
- ✅ UPS charge trend detection
- ✅ Host status transitions
- ✅ State persistence
- ✅ Notification sending
- ✅ Parallel processing
- ✅ Configuration handling

### Edge Cases
- ✅ SNMP failures
- ✅ Network timeouts
- ✅ Missing configuration
- ✅ Corrupted state files
- ✅ Process failures

### Integration
- ✅ CLI runner workflow
- ✅ Notification adapters
- ✅ State management
- ✅ Lock handling

## Writing Tests

### Test Structure
```php
test('description of what it does', function () {
    // Arrange
    $mockData = createMockState();
    
    // Act
    $result = someFunction($mockData);
    
    // Assert
    expect($result)->toBe('expected');
});
```

### Helper Functions
```php
// Create mock state data
$state = createMockState(['status' => 'warning']);

// Create mock UPS configuration
$upsConfig = createMockUpsConfig(['ip' => '192.168.1.100']);

// Create mock host configuration
$hostConfig = createMockHostConfig(['name' => 'Test Host']);

// Create temporary file
$tempFile = createTempFile('test.json');

// Clean up temporary file
cleanupTempFile('test.json');
```

### Mocking Dependencies
```php
beforeEach(function () {
    $this->mockService = Mockery::mock(SomeService::class);
    $this->mockService->shouldReceive('someMethod')->andReturn('result');
});

afterEach(function () {
    Mockery::close();
});
```

## Continuous Integration

### GitHub Actions
```yaml
- name: Run Tests
  run: vendor/bin/pest --coverage
  
- name: Upload Coverage
  uses: codecov/codecov-action@v3
```

### Local Development
```bash
# Install dependencies
composer install --dev

# Run tests
vendor/bin/pest

# Check coverage
vendor/bin/pest --coverage --min=80
```

## Best Practices

1. **Test one thing at a time** - Each test should verify a single behavior
2. **Use descriptive names** - Test names should clearly describe what they test
3. **Arrange-Act-Assert** - Structure tests clearly
4. **Mock external dependencies** - Don't rely on real network calls
5. **Clean up after tests** - Remove temporary files and reset state
6. **Cover edge cases** - Test error conditions and boundary cases
7. **Maintain high coverage** - Aim for 80%+ coverage

## Troubleshooting

### Common Issues
- **pcntl not available** - Tests will be skipped gracefully
- **Missing dependencies** - Run `composer install --dev`
- **Permission issues** - Ensure temp directory is writable
- **Mockery conflicts** - Close mocks in `afterEach()`

### Debugging Tests
```bash
# Run with verbose output
vendor/bin/pest --verbose

# Stop on first failure
vendor/bin/pest --stop-on-failure

# Run specific test with debug
vendor/bin/pest --filter="test name" --debug
```
