<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportingJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->organization_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'form_type' => ['required', 'string'],
            'method' => ['required', 'string', 'in:Playwright,DirectHTTP,API'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];

        if (in_array($this->input('method'), ['Playwright', 'DirectHTTP'])) {
            $rules['nap_username'] = ['required', 'string'];
            $rules['nap_password'] = ['required', 'string'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nap_username.required' => 'NAP username is required for Playwright automation.',
            'nap_password.required' => 'NAP password is required for Playwright automation.',
            'file.mimes' => 'File must be an Excel (.xlsx, .xls) or CSV file.',
            'file.max' => 'File must be less than 10 MB.',
        ];
    }
}
