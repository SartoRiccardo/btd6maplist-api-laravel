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

## Base tests classes traits

- Study very well the base class @tests/TestCase.php as there are many useful helper methods you can use in all tests.
- The traits in @tests/Traits package lots of useful pre-made tests you can inject in any route. This is useful, for example, to automatically test authentication on a route. You should get to know what's in that folder!

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
$format = Format::factory()->create();
$user = $this->createUserWithPermissions([$format->id => ['edit:config']]);

$payload = [
    ...$this->requestData(),
    'hidden' => true,
    'run_submission_status' => 'lcc_only',
    'map_submission_status' => 'open_chimps',
    'map_submission_wh' => fake()->url(),
    'run_submission_wh' => fake()->url(),
    'emoji' => fake()->emoji(),
];

$this->actingAs($user, 'discord')
    ->putJson('/api/formats/' . $format->id, $payload)
    ->assertStatus(204);

// Verify with GET
$actual = $this->actingAs($user, 'discord')
    ->getJson('/api/formats/' . $format->id)
    ->assertStatus(200)
    ->json();

$expected = Format::jsonStructure([
    ...$format->toArray(),
    ...$payload,
], strict: false);

$this->assertEquals($expected, $actual);
```

### Final Assertion

Always use `->assertEquals($expected, $actual)` for final assertion. Use the model's `jsonStructure()` method from the TestableStructure trait to craft response schemas. Combine model data and payload using spread operator: `[...$model->toArray(), ...$payload]`. Use `strict: false` to ignore extra keys from defaults().

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
- Assertions must, most of the time, happen with a single `->assertEquals` call. Use the model's `jsonStructure()` method (from TestableStructure trait) to craft correct response schemas. Combine the model's array representation with your payload using spread operator: `[...$model->toArray(), ...$payload]`.

## Existing Tests

You may look at existing tests to know the code style and patterns we currently use. Some good example tests can be found in @tests/Feature/Formats
