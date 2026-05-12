<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SmsBlastRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:160',
            'type' => 'required|string|max:50',
            'slug' => 'nullable|string|max:50',
            'message' => 'required|string|max:255',
            'send_mode' => 'required|string|max:50',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'recipient_ids' => 'sometimes|nullable|array',
            'recipient_ids.*' => 'string|max:12',
        ];
    }
}
