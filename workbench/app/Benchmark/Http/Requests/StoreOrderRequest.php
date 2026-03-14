<?php

namespace App\Benchmark\Http\Requests;

use App\Benchmark\Enums\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => 'required|string|max:64',
            'channel' => ['required', Rule::in(array_column(Channel::cases(), 'value'))],
            'express' => 'nullable|boolean',
            'priority' => 'nullable|string|in:low,normal,high',
            'manual_review' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required|string|max:80',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'shipping' => 'required|array',
            'shipping.line1' => 'required|string|max:120',
            'shipping.line2' => 'nullable|string|max:120',
            'shipping.city' => 'required|string|max:120',
            'shipping.postal_code' => 'required|string|max:20',
            'shipping.country' => 'required|string|size:2',
        ];
    }
}
