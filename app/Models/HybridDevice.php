<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HybridDevice extends Model
{
    protected $fillable = [
        'name',
        'token_hash',
        'allow_attendance',
        'allow_opac',
        'allow_catalog',
        'last_seen_at',
        'last_sync_at',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allow_attendance' => 'boolean',
            'allow_opac' => 'boolean',
            'allow_catalog' => 'boolean',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function catalogPushes(): HasMany
    {
        return $this->hasMany(HybridCatalogPush::class);
    }

    /** @return array{device: self, plain_token: string} */
    public static function issue(string $name, array $caps = []): array
    {
        $plain = 'hyb_'.Str::random(48);

        $device = static::create([
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'allow_attendance' => $caps['allow_attendance'] ?? true,
            'allow_opac' => $caps['allow_opac'] ?? true,
            'allow_catalog' => $caps['allow_catalog'] ?? false,
            'is_active' => true,
            'notes' => $caps['notes'] ?? null,
        ]);

        return ['device' => $device, 'plain_token' => $plain];
    }

    public function reissueToken(): string
    {
        $plain = 'hyb_'.Str::random(48);
        $this->forceFill(['token_hash' => hash('sha256', $plain)])->save();

        return $plain;
    }

    public static function findByToken(string $plainToken): ?self
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return null;
        }

        return static::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('is_active', true)
            ->first();
    }

    public static function resolveFromRequest(Request $request): ?self
    {
        $token = $request->header('X-Hybrid-Token')
            ?: $request->header('X-Device-Token')
            ?: $request->input('device_token')
            ?: $request->bearerToken();

        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        $device = static::findByToken($token);
        if ($device) {
            $device->touchSeen();
        }

        return $device;
    }

    public function touchSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    public function touchSynced(): void
    {
        $this->forceFill([
            'last_seen_at' => now(),
            'last_sync_at' => now(),
        ])->save();
    }

    public function allows(string $capability): bool
    {
        return match ($capability) {
            'attendance' => (bool) $this->allow_attendance,
            'opac' => (bool) $this->allow_opac,
            'catalog' => (bool) $this->allow_catalog,
            default => false,
        };
    }
}
