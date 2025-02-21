<?php
namespace App\Api\V1\Controllers\RollingReserve\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowRollingReserveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:rolling_reserve_entries,id'
        ];
    }

    protected function prepareForValidation(): void
    {
        // Get the id from the route parameter and merge it into the request
        $this->merge(['id' => $this->route('id')]);
    }
}
