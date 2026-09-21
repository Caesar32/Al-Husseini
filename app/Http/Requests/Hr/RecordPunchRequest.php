<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class RecordPunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required_without:pin', 'nullable', 'exists:employees,id'],
            'pin' => ['required_without:employee_id', 'nullable', 'string', 'exists:employees,zkteco_pin'],
            'timestamp' => ['required', 'date'],
            'punch_state' => ['required', 'in:0,1,check_in,check_out'],
        ];
    }
}
