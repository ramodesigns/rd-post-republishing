<?php

namespace Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Calculation_Helper;
use PHPUnit\Framework\TestCase;

class CalculationHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test date validation scenarios
     */
    public function test_validate_date()
    {
        $helper = new Calculation_Helper();

        $this->assertEquals(['date is missing'], $helper->validate_date(null));
        $this->assertEquals(['date is missing'], $helper->validate_date(''));
        $this->assertEquals(['date is invalid'], $helper->validate_date(123));
        $this->assertEquals(['date is invalid'], $helper->validate_date('2024-01-01')); // wrong format
        $this->assertEquals(['date is invalid'], $helper->validate_date('31-04-2024')); // April 31st (not real)
        $this->assertEmpty($helper->validate_date('01-01-2024'));
    }

    /**
     * Test calculation validation scenarios
     */
    public function test_validate_calculation()
    {
        $helper = new Calculation_Helper();

        $this->assertEquals('Operation is required', $helper->validate_calculation(null, []));
        $this->assertEquals('Operation must be a string', $helper->validate_calculation(123, []));
        $this->assertEquals('Invalid operation. Valid operations are: sum, average, min, max, count', $helper->validate_calculation('invalid', []));
        $this->assertEquals('Values are required', $helper->validate_calculation('sum', null));
        $this->assertEquals('Values must be an array', $helper->validate_calculation('sum', 'string'));
        $this->assertEquals('Value at index 1 must be numeric', $helper->validate_calculation('sum', [1, 'a']));
        $this->assertNull($helper->validate_calculation('sum', [1, 2, 3]));
    }

    /**
     * Test deterministic time generation logic
     */
    public function test_generate_post_times()
    {
        $helper = new Calculation_Helper();

        // Mock home_url() for the domain fallback
        Functions\expect('home_url')
            ->andReturn('https://example.com');

        // Test with explicit domain
        $times1 = $helper->generate_post_times('01-01-2024', 9, 17, 4, 'test.com');
        $times2 = $helper->generate_post_times('01-01-2024', 9, 17, 4, 'test.com');
        $this->assertEquals($times1, $times2); // Determinism

        // Test with different domain
        $times3 = $helper->generate_post_times('01-01-2024', 9, 17, 4, 'other.com');
        $this->assertNotEquals($times1, $times3);

        // Test domain fallback (uses home_url)
        $times_fallback = $helper->generate_post_times('01-01-2024', 9, 17, 4);
        $this->assertIsArray($times_fallback);
        $this->assertCount(4, $times_fallback);
    }
}
