<?php

namespace App\Services\ChannelManager;

use App\Models\Property;
use App\Services\ChannelManager\Contracts\ChannelManagerDriver;
use App\Services\ChannelManager\Drivers\AxisRoomsDriver;
use App\Services\ChannelManager\Drivers\NullDriver;

/**
 * Resolves the right driver for a property's tenant configuration.
 *
 * Tenant-level config (Tenant::channel_manager_driver) wins.
 * Falls back to env default. Falls to NullDriver if nothing configured
 * (so the rest of the system can call channel manager methods safely
 * even on tenants who haven't set anything up).
 */
class ChannelManagerFactory
{
    /**
     * @var array<string,class-string<ChannelManagerDriver>>
     */
    protected static array $drivers = [
        'axisrooms' => AxisRoomsDriver::class,
        // 'staah' => StaahDriver::class,
        // 'siteminder' => SiteMinderDriver::class,
        'null' => NullDriver::class,
    ];

    public static function forProperty(Property $property): ChannelManagerDriver
    {
        $driverName = $property->tenant->channel_manager_driver
            ?? config('channel_manager.default_driver', 'null');

        $class = self::$drivers[$driverName] ?? self::$drivers['null'];

        return app($class);
    }

    public static function register(string $name, string $driverClass): void
    {
        self::$drivers[$name] = $driverClass;
    }

    public static function available(): array
    {
        return array_keys(self::$drivers);
    }
}
