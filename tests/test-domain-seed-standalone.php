<?php
/**
 * Standalone test for domain-based post time generation
 */

echo "=== Domain-Based Post Time Generation Tests ===\n\n";

/**
 * Generate deterministic post times based on the date and site domain
 */
function generate_post_times($date, $start_hour, $end_hour, $posts_per_day, $domain) {
    $times = array();

    // Convert hours to minutes from midnight
    $start_minutes = $start_hour * 60;
    $end_minutes = $end_hour * 60;
    $total_minutes = $end_minutes - $start_minutes;

    // Calculate segment size for each post
    $segment_size = $total_minutes / $posts_per_day;

    for ($i = 0; $i < $posts_per_day; $i++) {
        // Generate a deterministic offset within this segment using domain, date and index
        $segment_seed = crc32($domain . '_' . $date . '_' . $i);
        $offset_within_segment = abs($segment_seed) % (int) $segment_size;

        // Calculate the time in minutes from midnight
        $time_in_minutes = $start_minutes + ($i * $segment_size) + $offset_within_segment;

        // Convert to hours and minutes
        $hours = (int) floor($time_in_minutes / 60);
        $minutes = (int) ($time_in_minutes % 60);

        // Format as hh:mm
        $times[] = sprintf('%02d:%02d', $hours, $minutes);
    }

    return $times;
}

echo "--- Generating Times (4 posts/day, 9am-5pm) ---\n\n";

// Scenario 1: 26th Jan 2026 with domain bestbikeguides.co.uk (x2)
$t1a = generate_post_times('26-01-2026', 9, 17, 4, 'bestbikeguides.co.uk');
$t1b = generate_post_times('26-01-2026', 9, 17, 4, 'bestbikeguides.co.uk');
echo "26th Jan 2026 - bestbikeguides.co.uk (Call 1): " . implode(', ', $t1a) . "\n";
echo "26th Jan 2026 - bestbikeguides.co.uk (Call 2): " . implode(', ', $t1b) . "\n\n";

// Scenario 2: 26th Jan 2026 with domain bestbikeguides.com (x3)
$t2a = generate_post_times('26-01-2026', 9, 17, 4, 'bestbikeguides.com');
$t2b = generate_post_times('26-01-2026', 9, 17, 4, 'bestbikeguides.com');
$t2c = generate_post_times('26-01-2026', 9, 17, 4, 'bestbikeguides.com');
echo "26th Jan 2026 - bestbikeguides.com (Call 1): " . implode(', ', $t2a) . "\n";
echo "26th Jan 2026 - bestbikeguides.com (Call 2): " . implode(', ', $t2b) . "\n";
echo "26th Jan 2026 - bestbikeguides.com (Call 3): " . implode(', ', $t2c) . "\n\n";

// Scenario 3: 26th Jan 2026 with domain bestguitarequipment.com (x2)
$t3a = generate_post_times('26-01-2026', 9, 17, 4, 'bestguitarequipment.com');
$t3b = generate_post_times('26-01-2026', 9, 17, 4, 'bestguitarequipment.com');
echo "26th Jan 2026 - bestguitarequipment.com (Call 1): " . implode(', ', $t3a) . "\n";
echo "26th Jan 2026 - bestguitarequipment.com (Call 2): " . implode(', ', $t3b) . "\n\n";

// Scenario 4: 27th Jan 2026 with domain bestbikeguides.co.uk
$t4 = generate_post_times('27-01-2026', 9, 17, 4, 'bestbikeguides.co.uk');
echo "27th Jan 2026 - bestbikeguides.co.uk: " . implode(', ', $t4) . "\n\n";

// Scenario 5: 28th Jan 2026 with domain bestbikeguides.com
$t5 = generate_post_times('28-01-2026', 9, 17, 4, 'bestbikeguides.com');
echo "28th Jan 2026 - bestbikeguides.com: " . implode(', ', $t5) . "\n\n";

// Scenario 6: 29th Jan 2026 with domain bestguitarequipment.com
$t6 = generate_post_times('29-01-2026', 9, 17, 4, 'bestguitarequipment.com');
echo "29th Jan 2026 - bestguitarequipment.com: " . implode(', ', $t6) . "\n\n";

echo "--- Verification Tests ---\n\n";

// Test 1: Multiple calls with same domain should be identical
echo "TEST 1: Same domain + same date = identical times (deterministic)\n";
echo "  bestbikeguides.co.uk x2: " . ($t1a === $t1b ? "PASS ✓" : "FAIL ✗") . "\n";
echo "  bestbikeguides.com x3: " . ($t2a === $t2b && $t2b === $t2c ? "PASS ✓" : "FAIL ✗") . "\n";
echo "  bestguitarequipment.com x2: " . ($t3a === $t3b ? "PASS ✓" : "FAIL ✗") . "\n\n";

// Test 2: Same day with different domains should be different
echo "TEST 2: Same date + different domains = different times\n";
echo "  bestbikeguides.co.uk vs bestbikeguides.com: " . ($t1a !== $t2a ? "PASS ✓" : "FAIL ✗") . "\n";
echo "  bestbikeguides.co.uk vs bestguitarequipment.com: " . ($t1a !== $t3a ? "PASS ✓" : "FAIL ✗") . "\n";
echo "  bestbikeguides.com vs bestguitarequipment.com: " . ($t2a !== $t3a ? "PASS ✓" : "FAIL ✗") . "\n\n";

// Test 3: Different days with same domain should be different
echo "TEST 3: Different dates + same domain = different times\n";
echo "  bestbikeguides.co.uk: 26th vs 27th Jan: " . ($t1a !== $t4 ? "PASS ✓" : "FAIL ✗") . "\n";
echo "  bestbikeguides.com: 26th vs 28th Jan: " . ($t2a !== $t5 ? "PASS ✓" : "FAIL ✗") . "\n";
echo "  bestguitarequipment.com: 26th vs 29th Jan: " . ($t3a !== $t6 ? "PASS ✓" : "FAIL ✗") . "\n\n";

// Summary
$all_pass = ($t1a === $t1b) &&
            ($t2a === $t2b && $t2b === $t2c) &&
            ($t3a === $t3b) &&
            ($t1a !== $t2a) &&
            ($t1a !== $t3a) &&
            ($t2a !== $t3a) &&
            ($t1a !== $t4) &&
            ($t2a !== $t5) &&
            ($t3a !== $t6);

echo "--- Summary ---\n";
echo "All tests: " . ($all_pass ? "PASSED ✓" : "SOME FAILED ✗") . "\n";
