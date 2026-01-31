---
name: test-writer
description: Writes tests with the appropriate coding style. Use when you need to write unit or integration tests for the API.
---

# Testing

## Creating Tests

- Create tests using `php artisan make:test TestName`
    - Do NOT include `Feature/` in the command - tests are automatically created in `tests/Feature/`
    - Tests should use descriptive names: `test_user_can_create_format()`, not `test_create()`

## Test Metadata (CRITICAL)

**Always use PHP 8+ attributes, NEVER doc-comments:**

```php
// ✅ CORRECT - Use attributes
#[Group('post')]
#[Group('formats')]
public function test_create_format(): void
{
    // test code
}

// ❌ WRONG - Do not use doc-comments
/**
 * @test
 * @group post
 */
public function create_format(): void
{
    // This is deprecated and will break in PHPUnit 12
}
```

**Required grouping:**

- Mark tests by HTTP method: `#[Group('get')]`, `#[Group('post')]`, `#[Group('put')]`, `#[Group('patch')]`, `#[Group('delete')]`
- Add feature groups when useful: `#[Group('formats')]`, `#[Group('auth')]`
- Place attributes directly above the test method

## Running Tests

```bash
# Run all tests, stop on first failure
php artisan test --stop-on-failure

# Run specific group
php artisan test --group=post

# Run multiple groups
php artisan test --group=post --group=formats
```

## Base Test Classes and Traits

Study these files - they contain helper methods you'll use frequently:

- @tests/TestCase.php - Base class with authentication helpers, assertion utilities, and more
- @tests/Traits - Pre-built test patterns (e.g., automatic authentication testing for routes)

## Test Coding Style

### Avoid Useless Variables

Chain assertions instead of creating intermediate `$response` variables:

```php
// ❌ BAD - Unnecessary variable
$response = $this->getJson("/api/formats");
$response->assertStatus(200);
$actual = $response->json();

// ✅ GOOD - Clean chaining
$actual = $this->getJson("/api/formats")
    ->assertStatus(200)
    ->json();
```

### Integration Test Pattern (CRITICAL)

**Always verify with GET requests, never query the database directly:**

```php
// 1. Create/update something
$format = Format::factory()->create();
$user = $this->createUserWithPermissions([$format->id => ['edit:config']]);

$payload = [
    'hidden' => true,
    'run_submission_status' => 'lcc_only',
    'emoji' => fake()->emoji(),
];

$this->actingAs($user, 'discord')
    ->putJson('/api/formats/' . $format->id, $payload)
    ->assertStatus(204);

// 2. Verify with GET (not database query!)
$actual = $this->actingAs($user, 'discord')
    ->getJson('/api/formats/' . $format->id)
    ->assertStatus(200)
    ->json();

// 3. Single assertion using jsonStructure()
$expected = Format::jsonStructure([
    ...$format->toArray(),
    ...$payload,
], strict: false);

$this->assertEquals($expected, $actual);
```

**Why GET requests?** These are integration tests. We test the actual API behavior, not database state.

### Final Assertion Pattern

Always use `$this->assertEquals($expected, $actual)` for the final assertion:

1. Use the model's `jsonStructure()` method (from `TestableStructure` trait)
2. Combine model data with payload: `[...$model->toArray(), ...$payload]`
3. Use `strict: false` parameter (details to be added)

```php
$expected = Model::jsonStructure([
    ...$model->toArray(),
    ...$payload,
], strict: false);

$this->assertEquals($expected, $actual);
```

## Factory Usage

**Golden Rule: Only set values you actually assert against.**

Let the factory handle all defaults. Setting unnecessary values makes tests brittle and unclear.

```php
// ❌ BAD - Setting values that aren't tested
Config::factory()->create([
    'name' => 'points_top_map',
    'value' => '100.0',
    'type' => 'float',
    'description' => 'Points for the #1 map',  // Not asserted!
    'difficulty' => null,                       // Not asserted!
]);

// ✅ GOOD - Only what matters
Config::factory()->type('float')->forFormats([1])->create(['value' => '100.0']);
// Then assert: assertEquals(100.0, $actual['points_top_map']['value']);
```

### Factory Patterns

```php
// Single model
$app = OAuth2App::factory()->create();

// Multiple models
$apps = OAuth2App::factory()->count(3)->create();

// With sequence
$apps = OAuth2App::factory()
    ->count(3)
    ->sequence(fn(Sequence $seq) => [
        'created_at' => now()->subDays(3 - $seq->index)
    ])
    ->create();

// With specific attributes (only when you NEED them for assertions)
$specificName = 'Specific Name';
$app = OAuth2App::factory()->create(['name' => $specificName]);
// ... later assert against $specificName ...
```

### Factory Relationships (IMPORTANT)

**Always prefer built-in `->for()` methods for relationships:**

```php
// ✅ BEST - Use ->for() when available
$run = Run::factory()
    ->for($user)
    ->for($format)
    ->create();

// ⚠️ ACCEPTABLE - Only if ->for() isn't available
$run = Run::factory()->create([
    'user_id' => $user->id,
    'format_id' => $format->id,
]);
```

The `->for()` method is cleaner and uses the relationship definitions from the model.

### Custom Factory Methods

Factories may have custom methods like `->type('float')`, `->forFormats([1])`, etc. Check the factory class definition to see what's available - these methods make tests more readable than setting raw attributes.

## Entity Creation Strategy

If an entity is only used in a single test, create it inline using the factory. Don't create shared test data unless multiple tests need it.

```php
// ✅ Single-test entity - create inline
public function test_user_can_delete_own_format(): void
{
    $format = Format::factory()->create(['user_id' => $this->user->id]);
    // ... test logic ...
}
```

## Example Tests

See @tests/Feature/Formats for well-written examples following these patterns.

## Quick Reference

**Before writing tests, always:**

1. ✅ Check @tests/TestCase.php for helper methods
2. ✅ Check @tests/Traits for pre-built test patterns
3. ✅ Use PHP 8 attributes, not doc-comments
4. ✅ Verify with GET requests, not database queries
5. ✅ Use factories with minimal attributes
6. ✅ Prefer `->for()` for relationships
7. ✅ Chain assertions, avoid intermediate variables
8. ✅ Use single `assertEquals()` with `jsonStructure()` for final assertion
