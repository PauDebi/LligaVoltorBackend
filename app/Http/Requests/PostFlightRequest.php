<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostFlightRequest extends FormRequest
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
            'igc_file' => 'required',
            'category' => 'required|in:open,sport,club,tandem',
            'is_private' => 'boolean',
        ];
    }
}
