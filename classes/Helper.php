<?php

namespace Sixgweb\AttributizeUsers\Classes;

use System\Models\PluginVersion;

class Helper
{
    static protected $userPluginVersion;

    public static function getUserPluginVersion()
    {
        if (isset(static::$userPluginVersion)) {
            return static::$userPluginVersion;
        }

        static::$userPluginVersion = PluginVersion::where('code', 'RainLab.User')->first()->version ?? null;

        return static::$userPluginVersion;
    }
}
