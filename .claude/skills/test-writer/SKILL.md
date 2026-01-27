---
name: test-writer
description: Writes tests with the appropriate coding style. Use when you need to write unit or integration tests for the API.
---

# Testing

- To create tests, create them first with `php artisan make:test`.
    - You **do not** include `Feature/` when running this command. They are by default Feature tests and go in that directory.
- When you create seeders for test purposes, located in `seeders/test`, comment in a docstring exactly which tests they are used in. You need to specify the test's path and name (if used in every test of that file) and, optionally, the exact test name (mandatory if used in only one of the tests).
- If you only use an entity on a single test, create it in the test itself instead of using a seeder, using its model.
- Be meticuous when checking return values. Use AssertableJSON validators, and check the exact number of keys.
- To run tests, you can use `php artisan test --stop-on-failure`. You can also use the `--group` flag to select specific groups.
- You **must** mark tests by which http methods they are testing. for example, `@group post` in the comment.
- You **must** assert responses with the `->assertEquals` assertion. For non-deterministic values, such as IDs or timestamps, unset them before finally performing the `->assertEquals` assertion.

**Important**

- When you create or modify a test, create a comment for the test where you document what it does and add the identity of The Superuser (obtainable via `git config user.email`) in the comment. This way, future editors know who created the test and who they should ask to if one of them breaks. A good comment should look like the following:

```php
/**
 * Test getting an OAuth2 service successfully.
 *
 * @author some.guy@company.com
 * @author some.otherguy@company.com
 */
public function test_some_behavior(): void
```

If I am `the.superuser@company.com`, for example, you should turn the comment into the following:

```php
/**
 * Test getting an OAuth2 service successfully.
 *
 * @author some.guy@company.com
 * @author some.otherguy@company.com
 * @author the.superuser@company.com
 */
public function test_some_behavior(): void
```

## Base tests classes and WithTransaction trait

- Base test classes should inherit the `Tests\TransactionalTestCase` base class. This provides utility in speeding up tests.

## Test Coding and Assertion Style

### Assertion Style

- Avoid useless `$response` variables. For example, to get a json for a later assertion, refer to the following:

```php
// BAD! Do not do this.
$response = $this->getJson("/some/route");
$response->assertStatus(200);
$actual = $response->json();

// GOOD! Less clutter.
$actual = $this->getJson("/some/route")
  ->assertStatus(200)
  ->json();
```

### Integration Test Pattern

Always verify with GET requests, never query database directly:

```php
// Create/update something
$createResponse = $this->actingAs($admin, 'api')
    ->postJson('/api/v1/oauth2/apps', $payload)
    ->assertStatus(201)
    ->json();

// Verify with GET
$actual = $this->actingAs($admin, 'api')
    ->getJson('/api/v1/oauth2/apps/' . $createResponse['id'])
    ->assertStatus(200)
    ->json();

// Assert with assertEquals
JsonStructures::removeKeysRecursive($actual, ['id', 'created_at', 'updated_at']);
$expected = JsonStructures::oauth2App($payload, true, ['id', 'created_at', 'updated_at']);
$this->assertEquals($expected, $actual);
```

### Final Assertion

Always use `->assertEquals($expected, $actual)` for final assertion. For non-deterministic values (IDs, timestamps), use `JsonStructures::removeKeysRecursive()`. For response schemas, use `JsonStructures::{resourceName}()` helpers.

### Factory Usage

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

// With specific attributes (use when you REALLY NEED a specific value for some reason)
$specificName = 'Specific Name'
$app = OAuth2App::factory()->create(['name' => $specificName]);
// ... reuse $specificName in assertions if needed ...
```

- As most of the tests we write are Integration Tests, assertions must happen with calls to GET requests and checking the response, **never** by fetching directly in the database.
- Assertions must, most of the time, happen with a single `->assertEquals` call. You will have to use the `JsonStructures` helper class to craft correct response schemas. In those methods, you can even dump a whole model after creating it with a factory and calling the `->toArray` method on it.
