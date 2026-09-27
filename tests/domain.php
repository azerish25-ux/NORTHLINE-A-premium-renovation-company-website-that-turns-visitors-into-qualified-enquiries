<?php
declare(strict_types=1);
require dirname(__DIR__) . '/plugins/northline-planner/includes/Domain.php';
use Northline\Brief;
use Northline\Estimate;
use Northline\ValidationException;
$count = 0;
function check(bool $condition, string $message): void {
    global $count;
    if (!$condition) throw new RuntimeException($message);
    $count++;
    echo "PASS {$message}\n";
}
$input = ['type' => 'kitchen', 'propertySize' => '1800', 'area' => '200', 'finish' => 'considered', 'timeline' => '6-12', 'budget' => '100-200', 'name' => 'Alex Example', 'town' => 'Demo Town', 'priorities' => 'Improve daylight, circulation and practical storage.', 'email' => 'ALEX@example.com', 'phone' => '', 'consent' => true];
$brief = Brief::validate($input);
check($brief['email'] === 'alex@example.com', 'email normalization');
check($brief['area'] === 200, 'integer affected area');
$estimate = Estimate::calculate($brief);
check($estimate['construction'] === [60000, 90000], 'construction allowance');
check($estimate['design'] === [6000, 9000], 'design allowance');
check($estimate['contingency'] === [9900, 14850], 'contingency basis includes design');
check($estimate['total'] === [75000, 114000], 'outward rounding');
check(Estimate::calculate(array_replace($brief, ['propertySize' => 5000]))['total'] === $estimate['total'], 'property size does not inflate affected-area estimate');
check(Estimate::calculate(array_replace($brief, ['budget' => '500-plus']))['total'] === $estimate['total'], 'declared budget does not change price');
check(Estimate::calculate(array_replace($brief, ['area' => 500, 'budget' => '50-100']))['budgetBelowRange'], 'below-range budget flagged without rejection');
foreach (['type' => 'invalid', 'finish' => [], 'area' => '2e3', 'propertySize' => 12, 'email' => "bad\r\n@example.com", 'consent' => 'true', 'name' => 'A', 'priorities' => 'Too short'] as $key => $value) {
    try { Brief::validate(array_replace($input, [$key => $value])); throw new RuntimeException('Accepted invalid ' . $key); }
    catch (ValidationException $e) { check(isset($e->errors[$key]), 'reject invalid ' . $key); }
}
try { Brief::validate(array_replace($input, ['area' => 2000])); throw new RuntimeException('Accepted oversized kitchen'); }
catch (ValidationException $e) { check(isset($e->errors['area']), 'non-extension cannot exceed property'); }
check(Brief::validate(array_replace($input, ['type' => 'extension', 'area' => 2000]))['area'] === 2000, 'extension may exceed existing property area');
check(Brief::validate(array_replace($input, ['name' => '<b>Alex</b>']))['name'] === 'Alex', 'strip markup from stored text');
check(Brief::fingerprint($brief) === Brief::fingerprint(array_reverse($brief, true)), 'fingerprint independent of key order');
foreach (array_keys(Brief::TYPES) as $type) foreach (array_keys(Brief::FINISHES) as $finish) {
    $result = Estimate::calculate(array_replace($brief, ['type' => $type, 'finish' => $finish, 'area' => 237]));
    check($result['total'][0] <= $result['construction'][0] + $result['design'][0] + $result['contingency'][0] && $result['total'][1] >= $result['construction'][1] + $result['design'][1] + $result['contingency'][1], 'outward bounds ' . $type . '/' . $finish);
}
echo "{$count} domain assertions passed.\n";
