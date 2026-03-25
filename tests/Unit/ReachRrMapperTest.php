<?php

use App\Services\ReachRrMapper;

// --- KP → Risk Behavior Mapping ---

it('maps MSM to risk behavior index [1]', function () {
    expect(ReachRrMapper::riskBehaviorIndices('MSM'))->toBe([1]);
});

it('maps MSW to risk behavior index [2]', function () {
    expect(ReachRrMapper::riskBehaviorIndices('MSW'))->toBe([2]);
});

it('maps FSW to risk behavior index [2]', function () {
    expect(ReachRrMapper::riskBehaviorIndices('FSW'))->toBe([2]);
});

it('maps TG to risk behavior index [0]', function () {
    expect(ReachRrMapper::riskBehaviorIndices('TG'))->toBe([0]);
});

it('maps TGW to risk behavior index [0]', function () {
    expect(ReachRrMapper::riskBehaviorIndices('TGW'))->toBe([0]);
});

it('maps TGM to risk behavior index [0]', function () {
    expect(ReachRrMapper::riskBehaviorIndices('TGM'))->toBe([0]);
});

it('maps TGSW to risk behavior index [0]', function () {
    expect(ReachRrMapper::riskBehaviorIndices('TGSW'))->toBe([0]);
});

it('maps PWID to risk behavior index [3]', function () {
    expect(ReachRrMapper::riskBehaviorIndices('PWID'))->toBe([3]);
});

it('maps MIGRANT to risk behavior index [4]', function () {
    expect(ReachRrMapper::riskBehaviorIndices('MIGRANT'))->toBe([4]);
});

it('maps PRISONER to risk behavior index [5]', function () {
    expect(ReachRrMapper::riskBehaviorIndices('PRISONER'))->toBe([5]);
});

it('maps MALE to empty risk behavior', function () {
    expect(ReachRrMapper::riskBehaviorIndices('MALE'))->toBe([]);
});

it('maps FEMALE to empty risk behavior', function () {
    expect(ReachRrMapper::riskBehaviorIndices('FEMALE'))->toBe([]);
});

// --- KP → Target Group Mapping ---

it('maps MSM to target group index [0]', function () {
    expect(ReachRrMapper::targetGroupIndices('MSM'))->toBe([0]);
});

it('maps MSW to target group index [12]', function () {
    expect(ReachRrMapper::targetGroupIndices('MSW'))->toBe([12]);
});

it('maps FSW to target group index [15]', function () {
    expect(ReachRrMapper::targetGroupIndices('FSW'))->toBe([15]);
});

it('maps TGW to target group index [3]', function () {
    expect(ReachRrMapper::targetGroupIndices('TGW'))->toBe([3]);
});

it('maps TGM to target group index [6]', function () {
    expect(ReachRrMapper::targetGroupIndices('TGM'))->toBe([6]);
});

it('maps TGSW to target group index [9]', function () {
    expect(ReachRrMapper::targetGroupIndices('TGSW'))->toBe([9]);
});

it('maps PWID to target group index [1]', function () {
    expect(ReachRrMapper::targetGroupIndices('PWID'))->toBe([1]);
});

it('maps MIGRANT to target group index [16]', function () {
    expect(ReachRrMapper::targetGroupIndices('MIGRANT'))->toBe([16]);
});

it('maps PRISONER to target group index [13]', function () {
    expect(ReachRrMapper::targetGroupIndices('PRISONER'))->toBe([13]);
});

it('maps MALE to target group index [14] (General Population)', function () {
    expect(ReachRrMapper::targetGroupIndices('MALE'))->toBe([14]);
});

it('maps FEMALE to target group index [14] (General Population)', function () {
    expect(ReachRrMapper::targetGroupIndices('FEMALE'))->toBe([14]);
});

// --- KP case insensitive ---

it('handles lowercase kp codes', function () {
    expect(ReachRrMapper::riskBehaviorIndices('msm'))->toBe([1]);
    expect(ReachRrMapper::targetGroupIndices('msm'))->toBe([0]);
});

// --- PWID Special Rules ---

it('adds extra knowledge indices for PWID', function () {
    expect(ReachRrMapper::knowledgeIndices('PWID'))->toBe([0, 1, 2, 3, 4]);
});

it('uses default knowledge indices for non-PWID', function () {
    expect(ReachRrMapper::knowledgeIndices('MSM'))->toBe([0, 1, 2]);
});

it('adds extra PPE index for PWID', function () {
    expect(ReachRrMapper::ppeIndices('PWID'))->toBe([0, 2, 3]);
});

it('uses default PPE indices for non-PWID', function () {
    expect(ReachRrMapper::ppeIndices('MSM'))->toBe([0, 2]);
});

// --- Occupation Mapping ---

it('maps Thai occupation text to code', function (string $text, string $expected) {
    expect(ReachRrMapper::occupationCode($text))->toBe($expected);
})->with([
    ['นักเรียน', '01'],
    ['นักศึกษา', '01'],
    ['student', '01'],
    ['ข้าราชการ', '02'],
    ['government', '02'],
    ['รับจ้าง', '03'],
    ['แรงงาน', '03'],
    ['general', '03'],
    ['พนักงานบริษัท', '04'],
    ['office', '04'],
    ['ค้าขาย', '05'],
    ['self-employed', '05'],
    ['ธุรกิจ', '05'],
    ['ว่างงาน', '06'],
    ['unemployed', '06'],
]);

it('defaults to occupation code 03 for unknown text', function () {
    expect(ReachRrMapper::occupationCode('unknown'))->toBe('03');
    expect(ReachRrMapper::occupationCode(''))->toBe('03');
});

// --- UIC → Birthdate ---

it('extracts birthdate from UIC last 6 chars', function () {
    expect(ReachRrMapper::uicToBirthdate('ABCDEFG020785'))->toBe('1985-07-02');
});

it('handles UIC with year > 70 as 19xx', function () {
    expect(ReachRrMapper::uicToBirthdate('ABCDEFG150371'))->toBe('1971-03-15');
});

it('handles UIC with year <= 70 as 20xx', function () {
    expect(ReachRrMapper::uicToBirthdate('ABCDEFG010105'))->toBe('2005-01-01');
});

it('returns null for UIC shorter than 6 chars', function () {
    expect(ReachRrMapper::uicToBirthdate('ABC'))->toBeNull();
});

// --- Thai Date Conversion ---

it('converts CE date to Thai Buddhist date dd/mm/yyyy', function () {
    expect(ReachRrMapper::toThaiDate('2025-07-02'))->toBe('02/07/2568');
});

it('converts another CE date to Thai Buddhist date', function () {
    expect(ReachRrMapper::toThaiDate('2026-03-25'))->toBe('25/03/2569');
});

it('returns null for invalid date', function () {
    expect(ReachRrMapper::toThaiDate('invalid'))->toBeNull();
});

// --- Birthdate to Thai Date ---

it('converts birthdate from UIC to Thai date format', function () {
    expect(ReachRrMapper::uicToThaiBirthdate('ABCDEFG020785'))->toBe('02/07/2528');
});

// --- Access Type Mapping ---

it('maps access type to selector id', function (string|int $input, string $expected) {
    expect(ReachRrMapper::accessTypeSelector($input))->toBe($expected);
})->with([
    [1, '#access_type_1'],
    [2, '#access_type_2'],
    [3, '#access_type_3'],
    ['1', '#access_type_1'],
]);

it('defaults access type to 2 (นอก DIC)', function () {
    expect(ReachRrMapper::accessTypeSelector(null))->toBe('#access_type_2');
    expect(ReachRrMapper::accessTypeSelector(0))->toBe('#access_type_2');
});

// --- Forward Service Mapping ---

it('maps forward service value to selector suffix', function () {
    expect(ReachRrMapper::forwardSelector('hiv', 1))->toBe('#hiv_forward_1');
    expect(ReachRrMapper::forwardSelector('hiv', 2))->toBe('#hiv_forward_2');
    expect(ReachRrMapper::forwardSelector('sti', 3))->toBe('#sti_forward_3');
    expect(ReachRrMapper::forwardSelector('tb', 1))->toBe('#tb_forward_1');
});

it('defaults forward service to 3 (ไม่ส่งต่อ)', function () {
    expect(ReachRrMapper::forwardSelector('hiv', null))->toBe('#hiv_forward_3');
});

// --- Build Full Form Data ---

it('builds complete form data from a row', function () {
    $row = [
        'pid' => '1234567890123',
        'uic' => 'TESTUSER020785',
        'kp' => 'MSM',
        'service_date' => '2025-07-02',
        'occupation' => 'นักเรียน',
        'access_type' => 2,
        'condom_49' => 10,
        'condom_52' => 5,
        'condom_53' => 0,
        'condom_54' => 0,
        'condom_56' => 0,
        'female_condom' => 0,
        'lubricant' => 20,
        'next_hcode' => '41936',
        'hiv_forward' => 1,
        'sti_forward' => 3,
        'tb_forward' => 3,
    ];

    $formData = ReachRrMapper::buildFormData($row);

    expect($formData)
        ->pid->toBe('1234567890123')
        ->risk_behavior_indices->toBe([1])
        ->target_group_indices->toBe([0])
        ->knowledge_indices->toBe([0, 1, 2])
        ->ppe_indices->toBe([0, 2])
        ->occupation_code->toBe('01')
        ->access_type_selector->toBe('#access_type_2')
        ->service_date_thai->toBe('02/07/2568')
        ->birthdate_thai->toBe('02/07/2528')
        ->condom_49->toBe(10)
        ->condom_52->toBe(5)
        ->lubricant->toBe(20)
        ->next_hcode->toBe('41936')
        ->hiv_forward_selector->toBe('#hiv_forward_1')
        ->sti_forward_selector->toBe('#sti_forward_3')
        ->tb_forward_selector->toBe('#tb_forward_3');
});

// --- Condom Defaults ---

it('applies default condom values when zero', function () {
    $row = [
        'pid' => '1234567890123',
        'uic' => 'TESTUSER020785',
        'kp' => 'MSM',
        'service_date' => '2025-07-02',
        'condom_49' => 0,
        'condom_52' => 0,
        'condom_53' => 0,
        'condom_54' => 0,
        'condom_56' => 0,
        'female_condom' => 0,
        'lubricant' => 0,
        'next_hcode' => '41936',
    ];

    $formData = ReachRrMapper::buildFormData($row);

    expect($formData)
        ->condom_49->toBe(0)
        ->condom_52->toBe(20)
        ->condom_53->toBe(0)
        ->condom_54->toBe(20)
        ->condom_56->toBe(20)
        ->female_condom->toBe(0)
        ->lubricant->toBe(20);
});

it('keeps condom values when already provided', function () {
    $row = [
        'pid' => '1234567890123',
        'uic' => 'TESTUSER020785',
        'kp' => 'MSM',
        'service_date' => '2025-07-02',
        'condom_52' => 50,
        'condom_54' => 30,
        'condom_56' => 10,
        'lubricant' => 40,
        'next_hcode' => '41936',
    ];

    $formData = ReachRrMapper::buildFormData($row);

    expect($formData)
        ->condom_52->toBe(50)
        ->condom_54->toBe(30)
        ->condom_56->toBe(10)
        ->lubricant->toBe(40);
});
