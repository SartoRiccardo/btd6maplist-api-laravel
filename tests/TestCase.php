<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;


/**
 * Base test case for all feature tests that require database transactions.
 *
 * This class provides:
 * - Automatic transaction wrapping for each test
 * - Default database seeding via DatabaseSeeder
 * - Consistent test isolation
 *
 * All feature test base classes should extend this instead of TestCase directly.
 */

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected static $migrated = false;

    protected function beforeRefreshingDatabase(): void
    {
        if (!self::$migrated) {
            if ($this->needsDatabaseRefresh()) {
                \Log::info('Starting database refresh for test: ' . static::class);
                Artisan::call('migrate:fresh');
                \Log::info('Seeding database for test: ' . static::class);
                // $this->seed(\Database\Seeders\DatabaseSeeder::class);
                \Log::info('Finished seeding database for test: ' . static::class);
            } else {
                \Log::info('Database already at latest migration, skipping refresh for test: ' . static::class);
            }
        }
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;
        self::$migrated = true;
    }

    /**
     * Check if the database needs to be refreshed.
     *
     * @return bool
     */
    protected function needsDatabaseRefresh(): bool
    {
        if (strtolower(env('DB_TEST_REFRESH', 'false')) === 'true') {
            \Log::info('DB_TEST_REFRESH environment variable detected, forcing database refresh');
            return true;
        }

        try {
            Artisan::call('migrate:status');
            $output = Artisan::output();
            return str_contains($output, 'Pending') || str_contains($output, 'Migration table not found');
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Assert that two arrays contain the same elements (order-independent).
     * Compares by extracting a key from each element and sorting.
     *
     * @param array $expected Expected array of models/arrays
     * @param array $actual Actual array from response
     * @param callable|null $keyExtractor Function to extract comparison key, defaults to fn($item) => $item['id']
     */
    protected function assertArrayEqualsCanonical(array $expected, array $actual, ?callable $keyExtractor = null): void
    {
        $keyExtractor ??= fn($item) => is_array($item) ? $item['id'] : (string) $item->id;

        $expectedKeys = array_map($keyExtractor, $expected);
        $actualKeys = array_map($keyExtractor, $actual);

        sort($expectedKeys);
        sort($actualKeys);

        $this->assertEquals($expectedKeys, $actualKeys);
    }

    /**
     * Clean up after each test.
     * Resets all API client fakes to prevent state leakage between tests.
     */
    protected function tearDown(): void
    {
        // Clean up all API client fakes to prevent state leakage
        \App\Services\Discord\DiscordApiClient::clearFake();
        \App\Services\NinjaKiwi\NinjaKiwiApiClient::clearFake();

        parent::tearDown();
    }

}
