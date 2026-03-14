<?php

namespace App\Benchmark\Support;

use App\Benchmark\Enums\Channel;
use Illuminate\Validation\Rule;

class RuleFactory
{
    /**
     * @return array<string, list<string|object>>
     */
    public static function create(Channel $channel): array
    {
        $channelRules = match ($channel) {
            Channel::Web => ['required', 'string', 'max:120'],
            Channel::Api => ['required', 'string', 'max:80'],
            Channel::Admin => ['required', 'string', 'max:200'],
        };

        return [
            'reference' => ['required', 'string', 'max:64'],
            'channel' => ['required', Rule::in(array_column(Channel::cases(), 'value'))],
            'notes' => ['nullable', ...$channelRules],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
