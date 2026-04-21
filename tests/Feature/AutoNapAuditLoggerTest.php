<?php

use App\Services\AutoNapAuditLogger;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['services.autonap.audit_enabled' => true]);
    config(['services.autonap.audit_full_pid' => false]);
});

test('records a JSON snapshot for each job', function () {
    $items = [
        [
            'source_id' => '3484',
            'source' => 'Reach_clinic',
            'id_card' => '1529902058225',
            'uic' => 'รค280843',
            'rr_form' => [
                'pid' => '1529902058225',
                'access_type' => '2',
                'risk_behavior_indices' => [1],
            ],
        ],
    ];

    AutoNapAuditLogger::record('autonap-test123', 'mplus_cmi', 'RR', 'ศุภพล', $items);

    $files = Storage::disk('local')->allFiles('autonap_audit');
    expect($files)->toHaveCount(1);

    $contents = json_decode(Storage::disk('local')->get($files[0]), true);
    expect($contents['job_id'])->toBe('autonap-test123');
    expect($contents['site'])->toBe('mplus_cmi');
    expect($contents['form_type'])->toBe('RR');
    expect($contents['staff_name'])->toBe('ศุภพล');
    expect($contents['items'][0]['uic'])->toBe('รค280843');
    expect($contents['items'][0]['rr_form']['access_type'])->toBe('2');
});

test('masks PID by default', function () {
    $items = [[
        'id_card' => '1529902058225',
        'rr_form' => ['pid' => '1529902058225'],
    ]];

    AutoNapAuditLogger::record('autonap-mask', 'mplus_cmi', 'RR', null, $items);

    $files = Storage::disk('local')->allFiles('autonap_audit');
    $contents = json_decode(Storage::disk('local')->get($files[0]), true);

    expect($contents['items'][0]['id_card'])->toBe('xxxxxxxxx8225');
    expect($contents['items'][0]['rr_form']['pid'])->toBe('xxxxxxxxx8225');
});

test('preserves full PID when audit_full_pid is true', function () {
    config(['services.autonap.audit_full_pid' => true]);

    $items = [['id_card' => '1529902058225']];

    AutoNapAuditLogger::record('autonap-full', 'mplus_cmi', 'RR', null, $items);

    $files = Storage::disk('local')->allFiles('autonap_audit');
    $contents = json_decode(Storage::disk('local')->get($files[0]), true);

    expect($contents['items'][0]['id_card'])->toBe('1529902058225');
});

test('skips writing when audit_enabled is false', function () {
    config(['services.autonap.audit_enabled' => false]);

    AutoNapAuditLogger::record('autonap-off', 'mplus_cmi', 'RR', null, [['id_card' => '1234567890123']]);

    expect(Storage::disk('local')->allFiles('autonap_audit'))->toHaveCount(0);
});

test('groups files by date folder', function () {
    AutoNapAuditLogger::record('autonap-day', 'mplus_cmi', 'RR', null, [['id_card' => '1234567890123']]);

    $today = now('Asia/Bangkok')->format('Y-m-d');
    $files = Storage::disk('local')->allFiles("autonap_audit/{$today}");
    expect($files)->toHaveCount(1);
});
