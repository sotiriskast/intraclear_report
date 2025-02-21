<?php
namespace App\Api\V1\Controllers\RollingReserve\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListRollingReserveRequest extends FormRequest
{
    protected $validParams = [
        'status',
        'currency',
        'start_date',
        'end_date',
        'per_page'
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|string|in:pending,released',
            'start_date' => [
                'sometimes',
                'string',
                'date_format:Y-m-d',
                'before:tomorrow'
            ],
            'end_date' => [
                'sometimes',
                'string',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
                'before:tomorrow'
            ],
            'currency' => 'sometimes|string|size:3|in:EUR,USD,GBP',
            'per_page' => 'sometimes|integer|min:10|max:100'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Check for unknown parameters
            $unknownParams = array_diff(array_keys($this->all()), $this->validParams);

            foreach ($unknownParams as $param) {
                $closest = $this->findClosestMatch($param, $this->validParams);
                if ($closest) {
                    $validator->errors()->add(
                        $param,
                        "Unknown parameter '$param'. Did you mean '$closest'?"
                    );
                } else {
                    $validator->errors()->add(
                        $param,
                        "Unknown parameter '$param'. Valid parameters are: " . implode(', ', $this->validParams)
                    );
                }
            }
        });
    }

    private function findClosestMatch(string $input, array $possibilities): ?string
    {
        $shortest = -1;
        $closest = null;

        foreach ($possibilities as $possibility) {
            $lev = levenshtein($input, $possibility);
            if ($lev <= 3 && ($shortest < 0 || $lev < $shortest)) {
                $closest = $possibility;
                $shortest = $lev;
            }
        }

        return $closest;
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be either "pending" or "released"',
            'start_date.date_format' => 'Start date must be in YYYY-MM-DD format. Example: 2024-01-01',
            'start_date.before' => 'Start date cannot be in the future',
            'end_date.date_format' => 'End date must be in YYYY-MM-DD format. Example: 2024-12-31',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'end_date.before' => 'End date cannot be in the future',
            'currency.in' => 'Currency must be one of: EUR, USD, GBP',
            'currency.size' => 'Currency must be a 3-letter code'
        ];
    }}
