<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceAntiSpamSetting extends Model
{
    protected $fillable = [
        'session_id',
        'enabled',
        'max_messages_per_minute',
        'delay_between_messages_ms',
        'same_recipient_interval_seconds',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'max_messages_per_minute' => 'integer',
        'delay_between_messages_ms' => 'integer',
        'same_recipient_interval_seconds' => 'integer',
    ];

    public static function getForSession(string $sessionId): array
    {
        $record = static::where('session_id', $sessionId)->first();

        return [
            'enabled' => $record?->enabled ?? false,
            'max_messages_per_minute' => $record?->max_messages_per_minute ?? 20,
            'delay_between_messages_ms' => $record?->delay_between_messages_ms ?? 1000,
            'same_recipient_interval_seconds' => $record?->same_recipient_interval_seconds ?? 0,
        ];
    }

    public static function saveForSession(string $sessionId, array $data): void
    {
        static::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'enabled' => $data['enabled'] ?? false,
                'max_messages_per_minute' => max(1, (int) ($data['max_messages_per_minute'] ?? 20)),
                'delay_between_messages_ms' => max(0, (int) ($data['delay_between_messages_ms'] ?? 1000)),
                'same_recipient_interval_seconds' => max(0, (int) ($data['same_recipient_interval_seconds'] ?? 0)),
            ]
        );
    }
}
