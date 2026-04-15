<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HcodeValidatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcodeController extends Controller
{
    public function __construct(protected HcodeValidatorService $validator) {}

    /**
     * Look up a single hcode in the CPP registry.
     *
     * GET /api/hcode/lookup?hcode=41936
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hcode' => ['required', 'string', 'max:20'],
        ]);

        $data = $this->validator->lookup($validated['hcode']);

        if (! $data) {
            return response()->json([
                'valid' => false,
                'hcode' => $validated['hcode'],
                'message' => 'hcode not found in CPP registry',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            ...$data,
        ]);
    }

    /**
     * Bulk validation — accepts a list, returns which are valid and which are not.
     *
     * POST /api/hcode/validate-bulk  body: { hcodes: ["41936","00000"] }
     */
    public function validateBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hcodes' => ['required', 'array', 'min:1', 'max:100'],
            'hcodes.*' => ['string', 'max:20'],
        ]);

        $results = [];

        foreach ($validated['hcodes'] as $hcode) {
            $data = $this->validator->lookup($hcode);
            $results[] = [
                'hcode' => $hcode,
                'valid' => $data !== null,
                'name' => $data['name'] ?? null,
                'province' => $data['province'] ?? null,
            ];
        }

        return response()->json(['results' => $results]);
    }
}
