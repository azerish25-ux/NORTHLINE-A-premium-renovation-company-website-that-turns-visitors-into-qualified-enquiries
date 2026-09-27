<?php
/** Pure domain rules, shared by the WordPress adapter and executable tests. */
declare(strict_types=1);
namespace Northline;

final class ValidationException extends \InvalidArgumentException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Please check the highlighted fields.');
    }
}

final class Brief
{
    public const TYPES = ['kitchen' => 'Kitchen renovation', 'extension' => 'Home extension', 'whole-home' => 'Whole-home renovation'];
    public const FINISHES = ['considered' => 'Considered', 'signature' => 'Signature', 'bespoke' => 'Bespoke'];
    public const TIMELINES = ['0-3' => 'Within 3 months', '3-6' => '3–6 months', '6-12' => '6–12 months', '12-plus' => 'More than a year', 'flexible' => 'Flexible'];
    public const BUDGETS = ['50-100' => [50000, 100000], '100-200' => [100000, 200000], '200-350' => [200000, 350000], '350-500' => [350000, 500000], '500-plus' => [500000, null], 'exploring' => [null, null]];

    public static function validate(array $input): array
    {
        $errors = [];
        $out = [];
        foreach (['type' => self::TYPES, 'finish' => self::FINISHES, 'timeline' => self::TIMELINES, 'budget' => self::BUDGETS] as $key => $allowed) {
            $value = $input[$key] ?? null;
            if (!is_string($value) || !array_key_exists($value, $allowed)) {
                $errors[$key] = 'Choose one of the available options.';
            } else {
                $out[$key] = $value;
            }
        }
        foreach (['propertySize' => [100, 30000], 'area' => [50, 10000]] as $key => [$min, $max]) {
            $value = $input[$key] ?? null;
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]{1,5}$/D', (string) $value) || (int) $value < $min || (int) $value > $max) {
                $errors[$key] = sprintf('Enter a whole number between %s and %s square feet.', number_format($min), number_format($max));
            } else {
                $out[$key] = (int) $value;
            }
        }
        if (isset($out['type'], $out['area'], $out['propertySize']) && $out['type'] !== 'extension' && $out['area'] > $out['propertySize']) {
            $errors['area'] = 'The area being renovated cannot exceed the existing property size.';
        }
        foreach (['name' => [2, 100], 'town' => [2, 120], 'priorities' => [20, 3000]] as $key => [$min, $max]) {
            $value = $input[$key] ?? null;
            if (!is_string($value) || !preg_match('//u', $value)) {
                $errors[$key] = 'Please enter valid text.';
                continue;
            }
            $value = trim(strip_tags($value));
            $length = preg_match_all('/./us', $value);
            if ($length < $min || $length > $max) {
                $errors[$key] = "Use between {$min} and {$max} characters.";
            } else {
                $out[$key] = $value;
            }
        }
        $email = $input['email'] ?? null;
        if (!is_string($email) || strlen($email) > 254 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address, such as alex@example.com.';
        } else {
            $out['email'] = strtolower(trim($email));
        }
        $phone = $input['phone'] ?? '';
        if (!is_string($phone) || ($phone !== '' && !preg_match('/^[+0-9() .-]{6,32}$/D', $phone))) {
            $errors['phone'] = 'Enter a phone number, or leave this optional field blank.';
        } else {
            $out['phone'] = trim($phone);
        }
        if (($input['consent'] ?? null) !== true) {
            $errors['consent'] = 'Please agree to the use of your details to discuss this project.';
        }
        $out['consent'] = true;
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $out;
    }

    public static function fingerprint(array $brief): string
    {
        ksort($brief);
        return hash('sha256', json_encode($brief, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}

final class Estimate
{
    /** Fictional demonstration allowances. These are NOT researched market prices or quotations. */
    public const RATE_CARD = [
        'version' => 'illustrative-v1-2026-09',
        'currency' => 'CAD',
        'rates' => ['kitchen' => [300, 450], 'extension' => [350, 525], 'whole-home' => [180, 280]],
        'finish' => ['considered' => 1.0, 'signature' => 1.25, 'bespoke' => 1.6],
        'designRate' => 0.10,
        'contingencyRate' => 0.15,
    ];

    public static function calculate(array $brief): array
    {
        $card = self::RATE_CARD;
        if (!isset($card['rates'][$brief['type'] ?? ''], $card['finish'][$brief['finish'] ?? '']) || !isset($brief['area']) || !is_numeric($brief['area']) || $brief['area'] < 50 || $brief['area'] > 10000) {
            throw new \InvalidArgumentException('A valid project type, finish and affected area are required.');
        }
        $construction = array_map(static fn ($rate): int => (int) round($rate * (float) $brief['area'] * $card['finish'][$brief['finish']]), $card['rates'][$brief['type']]);
        $design = array_map(static fn ($cost): int => (int) round($cost * $card['designRate']), $construction);
        $contingency = [];
        $total = [];
        foreach ([0, 1] as $i) {
            $contingency[$i] = (int) round(($construction[$i] + $design[$i]) * $card['contingencyRate']);
            $sum = $construction[$i] + $design[$i] + $contingency[$i];
            $total[$i] = (int) (($i === 0 ? floor($sum / 1000) : ceil($sum / 1000)) * 1000);
        }
        $budget = Brief::BUDGETS[$brief['budget'] ?? 'exploring'] ?? [null, null];
        return [
            'currency' => 'CAD', 'version' => $card['version'], 'area' => (int) $brief['area'],
            'construction' => $construction, 'design' => $design, 'contingency' => $contingency, 'total' => $total,
            'budgetBelowRange' => $budget[1] !== null && $budget[1] < $total[0],
            'assumptions' => [
                'Fictional portfolio allowances, not current local market data or a quotation.',
                'Calculated from the area affected by the project, not the entire property size.',
                'Assumes a sound existing structure, normal site access and a clearly defined scope.',
                'Includes a 10% design allowance and a 15% contingency on construction plus design.',
                'Excludes sales taxes, temporary accommodation, land, abnormal ground conditions, asbestos and major structural remediation.',
                'Rounded outwards to the nearest CAD 1,000. A site assessment and measured design are required before any priced proposal.',
            ],
        ];
    }
}
