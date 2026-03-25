<?php

use App\Services\ReachRrValidator;

function validRow(array $overrides = []): array
{
    return array_merge([
        'pid' => '1234567890123',
        'uic' => 'TESTUSER020785',
        'kp' => 'MSM',
        'service_date' => '2025-07-02',
        'occupation' => 'รับจ้าง',
        'access_type' => 2,
        'condom_49' => 10,
        'condom_52' => 0,
        'condom_53' => 0,
        'condom_54' => 0,
        'condom_56' => 0,
        'female_condom' => 0,
        'lubricant' => 5,
        'next_hcode' => '41936',
        'hiv_forward' => 1,
        'sti_forward' => 3,
        'tb_forward' => 3,
    ], $overrides);
}

// --- Valid Row ---

it('passes validation for a valid row', function () {
    $result = ReachRrValidator::validateRow(validRow());

    expect($result->isValid())->toBeTrue()
        ->and($result->errors)->toBeEmpty();
});

// --- PID Validation ---

it('fails when pid is missing', function () {
    $result = ReachRrValidator::validateRow(validRow(['pid' => '']));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('pid');
});

it('fails when pid is not 13 digits', function () {
    $result = ReachRrValidator::validateRow(validRow(['pid' => '12345']));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('pid');
});

it('fails when pid contains non-numeric chars', function () {
    $result = ReachRrValidator::validateRow(validRow(['pid' => '123456789012A']));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('pid');
});

// --- UIC Validation ---

it('fails when uic is missing', function () {
    $result = ReachRrValidator::validateRow(validRow(['uic' => '']));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('uic');
});

it('fails when uic is too short to extract birthdate', function () {
    $result = ReachRrValidator::validateRow(validRow(['uic' => 'AB']));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('uic');
});

// --- KP Validation ---

it('fails when kp is missing', function () {
    $result = ReachRrValidator::validateRow(validRow(['kp' => '']));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('kp');
});

it('fails when kp is not a valid code', function () {
    $result = ReachRrValidator::validateRow(validRow(['kp' => 'INVALID']));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('kp');
});

it('accepts all valid kp codes', function (string $kp) {
    $result = ReachRrValidator::validateRow(validRow(['kp' => $kp]));

    expect($result->isValid())->toBeTrue();
})->with([
    'MSM', 'MSW', 'FSW', 'TG', 'TGW', 'TGM', 'TGSW',
    'PWID', 'MIGRANT', 'PRISONER', 'MALE', 'FEMALE',
]);

it('accepts lowercase kp codes', function () {
    $result = ReachRrValidator::validateRow(validRow(['kp' => 'msm']));

    expect($result->isValid())->toBeTrue();
});

// --- Service Date Validation ---

it('fails when service_date is missing', function () {
    $result = ReachRrValidator::validateRow(validRow(['service_date' => '']));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('service_date');
});

it('fails when service_date is not a valid date', function () {
    $result = ReachRrValidator::validateRow(validRow(['service_date' => 'not-a-date']));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('service_date');
});

// --- Access Type Validation ---

it('fails when access_type is out of range', function () {
    $result = ReachRrValidator::validateRow(validRow(['access_type' => 5]));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('access_type');
});

it('allows null access_type (defaults to 2)', function () {
    $result = ReachRrValidator::validateRow(validRow(['access_type' => null]));

    expect($result->isValid())->toBeTrue();
});

// --- Forward Service Validation ---

it('fails when forward value is out of range', function () {
    $result = ReachRrValidator::validateRow(validRow(['hiv_forward' => 5]));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('hiv_forward');
});

it('allows null forward values (defaults to 3)', function () {
    $result = ReachRrValidator::validateRow(validRow([
        'hiv_forward' => null,
        'sti_forward' => null,
        'tb_forward' => null,
    ]));

    expect($result->isValid())->toBeTrue();
});

// --- Condom amounts non-negative ---

it('fails when condom amount is negative', function () {
    $result = ReachRrValidator::validateRow(validRow(['condom_49' => -1]));

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveKey('condom_49');
});

// --- Validate Multiple Rows ---

it('validates multiple rows and returns all results', function () {
    $rows = [
        validRow(),
        validRow(['pid' => 'INVALID']),
        validRow(['kp' => 'UNKNOWN']),
    ];

    $results = ReachRrValidator::validateRows($rows);

    expect($results)->toHaveCount(3)
        ->and($results[0]->isValid())->toBeTrue()
        ->and($results[1]->isValid())->toBeFalse()
        ->and($results[2]->isValid())->toBeFalse();
});

it('reports row numbers in validation results', function () {
    $rows = [
        validRow(),
        validRow(['pid' => '']),
    ];

    $results = ReachRrValidator::validateRows($rows);

    expect($results[0]->rowNumber)->toBe(2) // Excel row 2 (after header)
        ->and($results[1]->rowNumber)->toBe(3);
});
