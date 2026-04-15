<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoRequestController extends Controller
{
    /**
     * Accept a demo request from the landing page form.
     *
     * POST /api/demo-request
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'org_name' => ['required', 'string', 'max:300'],
            'province' => ['nullable', 'string', 'max:100'],
            'work_types' => ['required', 'array', 'min:1'],
            'work_types.*' => ['string', 'in:RR,VCT,Lab,Result,PrEP,HIVST,Other'],
            'cases_per_month' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'contact_name' => ['required', 'string', 'max:200'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $demoRequest = DemoRequest::create([
            ...$validated,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'status' => 'new',
        ]);

        return response()->json([
            'status' => 'ok',
            'id' => $demoRequest->id,
            'message' => 'ได้รับคำขอเรียบร้อย เราจะติดต่อกลับภายใน 24 ชั่วโมง',
        ]);
    }
}
