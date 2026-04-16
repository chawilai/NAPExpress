<?php

namespace App\Http\Controllers;

use App\Models\CppProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CppProviderController extends Controller
{
    /**
     * Paginated + filtered list.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'affiliation' => ['nullable', 'string', 'max:100'],
            'type_code' => ['nullable', 'string', 'max:20'],
            'has_email' => ['nullable', 'boolean'],
            'has_coordinator' => ['nullable', 'boolean'],
            'hiv_only' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:name,province,created_at'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = CppProvider::query()
            ->select([
                'id', 'hcode', 'name', 'affiliation', 'phone', 'uc_email',
                'subdistrict', 'district', 'province', 'postal_code', 'scraped_at',
            ])
            ->withCount('coordinators');

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($w) use ($q) {
                $w->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('hcode', 'LIKE', "%{$q}%")
                    ->orWhere('phone', 'LIKE', "%{$q}%");
            });
        }

        if (! empty($filters['province'])) {
            $query->where('province', $filters['province']);
        }

        if (! empty($filters['district'])) {
            $query->where('district', $filters['district']);
        }

        if (! empty($filters['affiliation'])) {
            $query->where('affiliation', $filters['affiliation']);
        }

        if (! empty($filters['type_code'])) {
            $code = $filters['type_code'];
            $query->whereHas('networkTypes', fn ($n) => $n->where('type_code', $code));
        }

        if (! empty($filters['has_email'])) {
            $query->where(function ($w) {
                $w->whereNotNull('uc_email')->where('uc_email', '!=', '')
                    ->orWhereHas('coordinators', fn ($c) => $c->whereNotNull('email')->where('email', '!=', ''));
            });
        }

        if (! empty($filters['has_coordinator'])) {
            $query->has('coordinators');
        }

        if (! empty($filters['hiv_only'])) {
            $query->where(function ($w) {
                $w->whereHas('networkTypes', fn ($n) => $n->where('type_code', 'R0216'));
                foreach (self::hivKeywords() as $kw) {
                    $w->orWhere('name', 'LIKE', "%{$kw}%");
                }
            });
        }

        $sort = $filters['sort'] ?? 'name';
        $query->orderBy($sort);

        $perPage = $filters['per_page'] ?? 25;
        $providers = $query->paginate($perPage)->withQueryString();

        // Facets for filter dropdowns — cached as plain arrays (avoid Collection serialization quirks)
        $provinces = cache()->remember('cpp:facet:provinces:v2', 3600, function () {
            return DB::table('cpp_providers')
                ->select('province', DB::raw('COUNT(*) as c'))
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->groupBy('province')
                ->orderByDesc('c')
                ->pluck('c', 'province')
                ->toArray();
        });

        $affiliations = cache()->remember('cpp:facet:affiliations:v2', 3600, function () {
            return DB::table('cpp_providers')
                ->select('affiliation', DB::raw('COUNT(*) as c'))
                ->whereNotNull('affiliation')
                ->groupBy('affiliation')
                ->orderByDesc('c')
                ->pluck('c', 'affiliation')
                ->toArray();
        });

        $typeCodes = cache()->remember('cpp:facet:types:v2', 3600, function () {
            return DB::table('cpp_provider_network_types')
                ->select('type_code', DB::raw('MIN(type_name) as type_name'), DB::raw('COUNT(DISTINCT cpp_provider_id) as c'))
                ->groupBy('type_code')
                ->orderByDesc('c')
                ->get()
                ->map(fn ($r) => [
                    'code' => $r->type_code,
                    'name' => $r->type_name,
                    'count' => $r->c,
                ])
                ->values()
                ->all();
        });

        return Inertia::render('CppProviders/Index', [
            'providers' => $providers,
            'filters' => $filters,
            'facets' => [
                'provinces' => $provinces,
                'affiliations' => $affiliations,
                'type_codes' => $typeCodes,
            ],
            'totals' => [
                'all' => cache()->remember('cpp:total', 3600, fn () => CppProvider::count()),
                'hiv_ecosystem' => cache()->remember('cpp:total:hiv:v2', 3600, function () {
                    return CppProvider::where(function ($q) {
                        $q->whereHas('networkTypes', fn ($n) => $n->where('type_code', 'R0216'));
                        foreach (self::hivKeywords() as $kw) {
                            $q->orWhere('name', 'LIKE', "%{$kw}%");
                        }
                    })->count();
                }),
            ],
        ]);
    }

    /**
     * HIV ecosystem keywords used in name-based fallback matching.
     *
     * @return array<int, string>
     */
    protected static function hivKeywords(): array
    {
        return ['ฟ้าสีรุ้ง', 'เอ็มพลัส', 'แคร์แมท', 'สวิง', 'ซิสเตอร์', 'เอ็มเฟรนด์'];
    }

    /**
     * Full provider detail page.
     */
    public function show(string $hcode): Response
    {
        $provider = CppProvider::with(['networkTypes', 'coordinators'])
            ->where('hcode', $hcode)
            ->firstOrFail();

        return Inertia::render('CppProviders/Show', [
            'provider' => $provider,
        ]);
    }
}
