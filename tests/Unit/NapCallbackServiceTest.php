<?php

use App\Services\NapCallbackService;

it('builds callback payload for success', function () {
    $rowData = [
        'rr_form' => [
            'pid' => '1550700153989',
        ],
        'identification' => [
            'pid' => '1550700153989',
            'uic' => 'ศอ160742',
        ],
        'person' => [
            'kp' => 'MSM',
        ],
        'context' => [
            'source' => 'clinic',
            'fy' => '2026',
        ],
        'service' => [
            'source_id' => 76140,
        ],
    ];

    $payload = NapCallbackService::buildPayload($rowData, 'RR-2026-1450737', 'success');

    expect($payload)
        ->source_id->toBe(76140)
        ->source->toBe('clinic')
        ->uic->toBe('ศอ160742')
        ->id_card->toBe('1550700153989')
        ->kp->toBe('MSM')
        ->fy->toBe('2026')
        ->nap_code->toBe('RR-2026-1450737')
        ->status->toBe('success')
        ->nap_staff->toBe('AutoNAP');
});

it('builds callback payload for error with comment', function () {
    $rowData = [
        'rr_form' => ['pid' => '1234567890123'],
        'identification' => ['pid' => '1234567890123', 'uic' => 'ทด010199'],
        'person' => ['kp' => 'FSW'],
        'context' => ['source' => 'reach', 'fy' => '2026'],
        'service' => ['source_id' => 99999],
    ];

    $payload = NapCallbackService::buildPayload($rowData, null, 'error', 'ข้อมูลซ้ำในระบบ');

    expect($payload)
        ->status->toBe('error')
        ->nap_code->toBeNull()
        ->nap_comment->toBe('ข้อมูลซ้ำในระบบ');
});

it('extracts fields from flat row_data (without nested structure)', function () {
    $rowData = [
        'rr_form' => [
            'pid' => '1550700153989',
            'rrttrDate' => '04/03/2569',
        ],
    ];

    $payload = NapCallbackService::buildPayload($rowData, 'RR-2026-001', 'success');

    expect($payload)
        ->id_card->toBe('1550700153989')
        ->nap_code->toBe('RR-2026-001')
        ->status->toBe('success');
});
