<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CppProvider;
use App\Models\CppProviderCoordinator;
use App\Models\CppProviderNetworkType;
use App\Models\CppScrapeQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CppScrapeController extends Controller
{
    /**
     * Worker claims next pending hcode atomically.
     */
    public function claim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'worker_id' => ['required', 'string', 'max:50'],
        ]);

        $claimed = DB::transaction(function () use ($validated) {
            $item = CppScrapeQueue::where('status', 'pending')
                ->where('phase', 'profile')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $item) {
                return null;
            }

            $item->update([
                'status' => 'claimed',
                'claimed_by' => $validated['worker_id'],
                'claimed_at' => now(),
                'attempts' => $item->attempts + 1,
            ]);

            return $item;
        });

        if (! $claimed) {
            return response()->json(['hcode' => null, 'message' => 'queue empty']);
        }

        return response()->json([
            'hcode' => $claimed->hcode,
            'attempts' => $claimed->attempts,
        ]);
    }

    /**
     * Worker reports scrape result (done | failed | not_found).
     */
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'worker_id' => ['required', 'string'],
            'hcode' => ['required', 'string'],
            'status' => ['required', 'string', 'in:done,failed,not_found'],
            'data' => ['nullable', 'array'],
            'error' => ['nullable', 'string'],
        ]);

        $queue = CppScrapeQueue::where('hcode', $validated['hcode'])->first();

        if (! $queue) {
            return response()->json(['message' => 'hcode not in queue'], 404);
        }

        if ($validated['status'] === 'done' && $validated['data']) {
            $this->saveProviderData($validated['hcode'], $validated['data']);
        }

        $queue->update([
            'status' => $validated['status'],
            'completed_at' => now(),
            'last_error' => $validated['error'] ?? null,
        ]);

        return response()->json(['message' => 'ok', 'status' => $validated['status']]);
    }

    /**
     * Bulk upsert basic provider info from Phase 1 list scraper.
     * Creates/updates cpp_providers with basic fields AND seeds cpp_scrape_queue.
     *
     * Body: { items: [ { hcode, name, phone, address_no, moo, soi, road, subdistrict, district, province, postal_code }, ... ] }
     */
    public function bulkUpsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.hcode' => ['required', 'string', 'max:20'],
            'items.*.name' => ['nullable', 'string'],
            'items.*.phone' => ['nullable', 'string'],
            'items.*.address_no' => ['nullable', 'string'],
            'items.*.moo' => ['nullable', 'string'],
            'items.*.soi' => ['nullable', 'string'],
            'items.*.road' => ['nullable', 'string'],
            'items.*.subdistrict' => ['nullable', 'string'],
            'items.*.district' => ['nullable', 'string'],
            'items.*.province' => ['nullable', 'string'],
            'items.*.postal_code' => ['nullable', 'string'],
        ]);

        $providersCreated = 0;
        $providersUpdated = 0;
        $queueCreated = 0;

        DB::transaction(function () use ($validated, &$providersCreated, &$providersUpdated, &$queueCreated) {
            foreach ($validated['items'] as $item) {
                $hcode = $item['hcode'];

                $existing = CppProvider::where('hcode', $hcode)->first();

                if ($existing) {
                    // Don't overwrite fields that Phase 2 already enriched (those have scraped_at set)
                    if ($existing->scraped_at === null) {
                        $existing->update([
                            'name' => $item['name'] ?? $existing->name,
                            'phone' => $item['phone'] ?? $existing->phone,
                            'address_no' => $item['address_no'] ?? $existing->address_no,
                            'moo' => $item['moo'] ?? $existing->moo,
                            'soi' => $item['soi'] ?? $existing->soi,
                            'road' => $item['road'] ?? $existing->road,
                            'subdistrict' => $item['subdistrict'] ?? $existing->subdistrict,
                            'district' => $item['district'] ?? $existing->district,
                            'province' => $item['province'] ?? $existing->province,
                            'postal_code' => $item['postal_code'] ?? $existing->postal_code,
                        ]);
                    }
                    $providersUpdated++;
                } else {
                    CppProvider::create([
                        'hcode' => $hcode,
                        'name' => $item['name'] ?? '',
                        'phone' => $item['phone'] ?? null,
                        'address_no' => $item['address_no'] ?? null,
                        'moo' => $item['moo'] ?? null,
                        'soi' => $item['soi'] ?? null,
                        'road' => $item['road'] ?? null,
                        'subdistrict' => $item['subdistrict'] ?? null,
                        'district' => $item['district'] ?? null,
                        'province' => $item['province'] ?? null,
                        'postal_code' => $item['postal_code'] ?? null,
                    ]);
                    $providersCreated++;
                }

                // Seed queue if not already present — don't disturb rows already processed
                $queue = CppScrapeQueue::where('hcode', $hcode)->first();

                if (! $queue) {
                    CppScrapeQueue::create([
                        'hcode' => $hcode,
                        'status' => 'pending',
                        'phase' => 'profile',
                    ]);
                    $queueCreated++;
                }
            }
        });

        return response()->json([
            'providers_created' => $providersCreated,
            'providers_updated' => $providersUpdated,
            'queue_created' => $queueCreated,
        ]);
    }

    /**
     * Status endpoint — useful for dashboards.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'total' => CppScrapeQueue::count(),
            'pending' => CppScrapeQueue::where('status', 'pending')->count(),
            'claimed' => CppScrapeQueue::where('status', 'claimed')->count(),
            'done' => CppScrapeQueue::where('status', 'done')->count(),
            'failed' => CppScrapeQueue::where('status', 'failed')->count(),
            'not_found' => CppScrapeQueue::where('status', 'not_found')->count(),
            'providers_saved' => CppProvider::count(),
        ]);
    }

    /**
     * Persist scraped provider data + relations.
     *
     * @param  array<string, mixed>  $data
     */
    protected function saveProviderData(string $hcode, array $data): void
    {
        DB::transaction(function () use ($hcode, $data) {
            // Normalize date (Thai Buddhist era "14 เมษายน 2569" → ce date)
            $lastUpdated = $this->parseThaiDate($data['cpp_last_updated'] ?? null);

            $provider = CppProvider::updateOrCreate(
                ['hcode' => $hcode],
                [
                    'name' => $data['name'] ?? '',
                    'registration_type' => $data['registration_type'] ?? null,
                    'affiliation' => $data['affiliation'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'website' => $data['website'] ?? null,
                    'service_plan_level' => $data['service_plan_level'] ?? null,
                    'operating_hours' => $data['operating_hours'] ?? null,

                    'address_no' => $data['address_no'] ?? null,
                    'moo' => $data['moo'] ?? null,
                    'soi' => $data['soi'] ?? null,
                    'road' => $data['road'] ?? null,
                    'subdistrict' => $data['subdistrict'] ?? null,
                    'district' => $data['district'] ?? null,
                    'province' => $data['province'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'local_admin_area' => $data['local_admin_area'] ?? null,

                    'uc_phone' => $data['uc_phone'] ?? null,
                    'quality_phone' => $data['quality_phone'] ?? null,
                    'referral_phone' => $data['referral_phone'] ?? null,
                    'uc_fax' => $data['uc_fax'] ?? null,
                    'uc_email' => $data['uc_email'] ?? null,
                    'doc_email' => $data['doc_email'] ?? null,

                    'cpp_last_updated' => $lastUpdated,
                    'scraped_at' => now(),
                ]
            );

            $provider->networkTypes()->delete();

            foreach (($data['network_types'] ?? []) as $net) {
                if (empty($net['type_code'])) {
                    continue;
                }

                CppProviderNetworkType::create([
                    'cpp_provider_id' => $provider->id,
                    'type_code' => $net['type_code'],
                    'type_name' => $net['type_name'] ?? '',
                ]);
            }

            $provider->coordinators()->delete();

            foreach (($data['coordinators'] ?? []) as $coord) {
                if (empty($coord['name']) && empty($coord['email']) && empty($coord['phone'])) {
                    continue;
                }

                CppProviderCoordinator::create([
                    'cpp_provider_id' => $provider->id,
                    'name' => $coord['name'] ?? null,
                    'email' => $coord['email'] ?? null,
                    'phone' => $coord['phone'] ?? null,
                    'mobile' => $coord['mobile'] ?? null,
                    'fax' => $coord['fax'] ?? null,
                    'department' => $coord['department'] ?? null,
                ]);
            }
        });
    }

    /**
     * Parse Thai Buddhist Era date string like "14 เมษายน 2569" to CE date.
     */
    protected function parseThaiDate(?string $thai): ?string
    {
        if (! $thai) {
            return null;
        }

        $months = [
            'มกราคม' => 1, 'กุมภาพันธ์' => 2, 'มีนาคม' => 3, 'เมษายน' => 4,
            'พฤษภาคม' => 5, 'มิถุนายน' => 6, 'กรกฎาคม' => 7, 'สิงหาคม' => 8,
            'กันยายน' => 9, 'ตุลาคม' => 10, 'พฤศจิกายน' => 11, 'ธันวาคม' => 12,
        ];

        if (! preg_match('/(\d{1,2})\s+(\S+)\s+(\d{4})/u', $thai, $m)) {
            return null;
        }

        $day = (int) $m[1];
        $monthTh = $m[2];
        $yearBE = (int) $m[3];

        if (! isset($months[$monthTh])) {
            return null;
        }

        $yearCE = $yearBE - 543;

        return sprintf('%04d-%02d-%02d', $yearCE, $months[$monthTh], $day);
    }
}
