<?php

namespace App\Support;

use App\Models\Setting;
use App\UserRole;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AttendanceDeviceSettings
{
    /** @return array<string, mixed> */
    public static function all(): array
    {
        return array_replace(config('attendance_device'), Setting::query()->first(['attendance_device'])?->attendance_device ?? []);
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public static function formState(): array
    {
        return Arr::only(self::all(), ['enabled', 'processing_enabled', 'host', 'port', 'transport', 'timeout', 'timezone']);
    }

    /** @param array<string, mixed> $data */
    public static function save(array $data): void
    {
        abort_unless(auth()->user()?->role === UserRole::Admin, 403);

        $validated = Validator::make($data, [
            'enabled' => ['required', 'boolean'],
            'processing_enabled' => ['sometimes', 'boolean'],
            'host' => ['required', 'string', 'max:253', 'regex:/\A[a-zA-Z0-9](?:[a-zA-Z0-9.\-]*[a-zA-Z0-9])?\z/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'transport' => ['required', Rule::in(['tcp', 'udp'])],
            'comm_key' => ['nullable', 'integer', 'between:0,4294967295'],
            'timeout' => ['required', 'numeric', 'between:1,15'],
            'timezone' => ['required', 'timezone'],
        ])->validate();

        if (blank($validated['comm_key'] ?? null)) {
            unset($validated['comm_key']);
        }

        DB::transaction(function () use ($validated): void {
            $settings = Setting::getSettings();
            $settings = Setting::query()->lockForUpdate()->findOrFail($settings->id);
            $settings->update(['attendance_device' => array_replace($settings->attendance_device ?? [], $validated)]);
        });
    }
}
