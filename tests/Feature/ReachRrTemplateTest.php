<?php

use App\Exports\ReportingTemplateExport;
use App\Models\Organization;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

it('downloads reach rr template with correct headers', function () {
    Excel::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('jobs.download-template', ['form_type' => 'Reach RR']))
        ->assertSuccessful();

    Excel::assertDownloaded('template_reach_rr.xlsx', function (ReportingTemplateExport $export) {
        $headings = $export->headings();
        $data = $export->array();

        expect($headings)->toContain('pid', 'uic', 'kp', 'service_date', 'occupation')
            ->toContain('access_type', 'condom_49', 'next_hcode', 'hiv_forward')
            ->and($headings)->toHaveCount(17)
            ->and($data)->toHaveCount(1)
            ->and($data[0][0])->toBe('1234567890123');

        return true;
    });
});

it('downloads generic template for non-reach-rr types', function () {
    Excel::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('jobs.download-template', ['form_type' => 'Lab CD4/VL']))
        ->assertSuccessful();

    Excel::assertDownloaded('template_lab_cd4/vl.xlsx', function (ReportingTemplateExport $export) {
        $headings = $export->headings();

        expect($headings)->toHaveCount(5)
            ->and($headings[0])->toBe('PID (13 digits)');

        return true;
    });
});
