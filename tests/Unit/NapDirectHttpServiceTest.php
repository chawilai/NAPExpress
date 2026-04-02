<?php

use App\Services\NapDirectHttpService;

// --- buildPreviewBody ---

it('builds preview POST body with correct static reference data', function () {
    $rrForm = [
        'rrttrDate' => '04/03/2569',
        'pid' => '1550700153989',
        'risk_behavior_indices' => [1],
        'target_group_indices' => [0],
        'access_type' => '2',
        'occupation' => '01',
        'condom' => ['49' => 0, '52' => 20, '53' => 0, '54' => 20, '56' => 20],
        'lubricant' => 20,
        'female_condom' => 0,
        'next_hcode' => '41681',
        'knowledge_indices' => [0, 1, 2],
        'place_indices' => [0, 1, 2],
        'ppe_indices' => [0, 2],
        'forwards' => ['hiv' => 2, 'sti' => 2, 'tb' => 2, 'hcv' => 3, 'methadone' => 3],
        'ref_tel' => '0617978524',
        'pay_by' => '1',
    ];

    $body = NapDirectHttpService::buildPreviewBody($rrForm);

    expect($body)
        ->toBeArray()
        ->and($body['actionName'])->toBe('preview')
        // Risk behaviors — static data always present
        ->and($body['rrttr_risk_behavior_0'])->toBe('1')
        ->and($body['rrttr_risk_behavior_name_1'])->toBe('MSM')
        // Only MSM (index 1) checked
        ->and($body['rrttr_risk_behavior_status_1'])->toBe('Y')
        ->and($body)->not->toHaveKey('rrttr_risk_behavior_status_0')
        // Target group — only MSM (index 0) checked
        ->and($body['rrttr_target_group_status_0'])->toBe('Y')
        ->and($body)->not->toHaveKey('rrttr_target_group_status_1')
        // Occupation
        ->and($body['occupation'])->toBe('01')
        ->and($body['occupation_name'])->toBe('ไม่มี/ว่างงาน')
        // Condoms
        ->and($body['rrttr_condom_amount_52'])->toBe('20')
        ->and($body['rrttr_condom_amount_54'])->toBe('20')
        ->and($body['rrttr_condom_amount_56'])->toBe('20')
        ->and($body['rrttr_lubricant_amount'])->toBe('20')
        // Forward services
        ->and($body['hiv_forward'])->toBe('2')
        ->and($body['hcv_forward'])->toBe('3')
        ->and($body['methadone_forward'])->toBe('3')
        // Access type
        ->and($body['access_type'])->toBe('2')
        // Phone
        ->and($body['ref_tel'])->toBe('0617978524')
        // Pay by
        ->and($body['pay_by'])->toBe('1')
        ->and($body['pay_by_name'])->toBe('NHSO')
        // Next hcode
        ->and($body['next_hcode'])->toBe('41681')
        // Knowledge — always all 5 checked
        ->and($body['rrttr_knowledge_status_0'])->toBe('Y')
        ->and($body['rrttr_knowledge_status_1'])->toBe('Y')
        ->and($body['rrttr_knowledge_status_2'])->toBe('Y')
        ->and($body['rrttr_knowledge_status_3'])->toBe('Y')
        ->and($body['rrttr_knowledge_status_4'])->toBe('Y')
        // Place
        ->and($body['rrttr_place_status_0'])->toBe('Y')
        ->and($body['rrttr_place_status_1'])->toBe('Y')
        ->and($body['rrttr_place_status_2'])->toBe('Y')
        // PPE
        ->and($body['rrttr_ppe_status_0'])->toBe('Y')
        ->and($body['rrttr_ppe_status_2'])->toBe('Y')
        ->and($body)->not->toHaveKey('rrttr_ppe_status_1');
});

// --- extractRrCode ---

it('extracts RR code from success HTML', function () {
    $html = '<html><body><div class="font22"><b>RR-2026-1450737</b></div></body></html>';

    expect(NapDirectHttpService::extractRrCode($html))->toBe('RR-2026-1450737');
});

it('returns null when no RR code in HTML', function () {
    $html = '<html><body><div>Error</div></body></html>';

    expect(NapDirectHttpService::extractRrCode($html))->toBeNull();
});

// --- extractError ---

it('extracts error message from NAP HTML', function () {
    $html = '<html><body><table class="alert"><tr><td class="text">ข้อมูลซ้ำในระบบ</td></tr></table></body></html>';

    expect(NapDirectHttpService::extractError($html))->toBe('ข้อมูลซ้ำในระบบ');
});

// --- Occupation name lookup ---

it('maps occupation code to Thai name', function () {
    expect(NapDirectHttpService::occupationName('01'))->toBe('ไม่มี/ว่างงาน');
    expect(NapDirectHttpService::occupationName('03'))->toBe('รับจ้างทั่วไป');
    expect(NapDirectHttpService::occupationName('20'))->toBe('นักเรียน/นักศึกษา');
    expect(NapDirectHttpService::occupationName('99'))->toBe('');
});

// --- Pay by name lookup ---

it('maps pay_by code to name', function () {
    expect(NapDirectHttpService::payByName('1'))->toBe('NHSO');
    expect(NapDirectHttpService::payByName('2'))->toBe('Global Fund');
    expect(NapDirectHttpService::payByName('99'))->toBe('');
});
