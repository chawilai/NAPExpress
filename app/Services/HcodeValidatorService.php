<?php

namespace App\Services;

use App\Models\CppProvider;
use Illuminate\Support\Facades\Cache;

class HcodeValidatorService
{
    /**
     * Check whether an hcode exists in the CPP providers registry.
     *
     * Results are cached 24h — the CPP registry changes infrequently.
     */
    public function exists(string $hcode): bool
    {
        $hcode = trim($hcode);

        if ($hcode === '') {
            return false;
        }

        return Cache::remember(
            "cpp:hcode:exists:{$hcode}",
            now()->addHours(24),
            fn () => CppProvider::where('hcode', $hcode)->exists()
        );
    }

    /**
     * Look up an hcode and return name + province for display/autocomplete.
     *
     * @return array{hcode:string,name:string,province:?string}|null
     */
    public function lookup(string $hcode): ?array
    {
        $hcode = trim($hcode);

        if ($hcode === '') {
            return null;
        }

        return Cache::remember(
            "cpp:hcode:lookup:{$hcode}",
            now()->addHours(24),
            function () use ($hcode) {
                $provider = CppProvider::where('hcode', $hcode)
                    ->select(['hcode', 'name', 'province', 'district'])
                    ->first();

                if (! $provider) {
                    return null;
                }

                return [
                    'hcode' => $provider->hcode,
                    'name' => $provider->name,
                    'province' => $provider->province,
                    'district' => $provider->district,
                ];
            }
        );
    }

    /**
     * Return "Name (Province)" for a given hcode, or null if not found.
     */
    public function displayName(string $hcode): ?string
    {
        $data = $this->lookup($hcode);

        if (! $data) {
            return null;
        }

        return trim($data['name'].($data['province'] ? ' ('.$data['province'].')' : ''));
    }
}
