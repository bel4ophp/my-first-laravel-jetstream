<?php

namespace App\Http\Requests;

use App\Enums\AttendanceExportPeriod;
use App\Models\TimeEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportAttendanceRequest extends FormRequest
{
    /**
     * Only admins, team owners and managers export attendance.
     */
    public function authorize(): bool
    {
        return $this->user()->can('export', TimeEntry::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AttendanceExportPeriod::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'week_date' => ['nullable', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_if:type,custom'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'required_if:type,custom', 'after_or_equal:start_date'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function period(): AttendanceExportPeriod
    {
        return $this->enum('type', AttendanceExportPeriod::class);
    }
}
