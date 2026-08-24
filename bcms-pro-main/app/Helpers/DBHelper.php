<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;

class DBHelper extends Model
{

    public static function getCFMSProUrl(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        $productionIP = config('params.server_ips.production');

        if ($serverIP == $productionIP) {
            return config('params.api_urls.cfms_pro.production');
        }

        return config('params.api_urls.cfms_pro.default');
    }

    public static function getGePGLink(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        $stagingIP = config('params.server_ips.staging');
        $developmentIP = config('params.server_ips.development');

        if ($serverIP == $stagingIP) {
            return config('params.api_urls.gepg.production');
        } elseif ($serverIP == $developmentIP) {
            return config('params.api_urls.gepg.development');
        }

        return config('params.api_urls.gepg.development');
    }

    public static function getEOfficeLink(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        if ($serverIP == '10.10.104.202') {
            return 'https://eoffice.nssf.go.tz:8081/';
        } elseif ($serverIP == '10.10.47.202') {
            return 'https://demo-eoffice.nssf.go.tz:8082/';
        }
        // Default to development/demo URL
        return 'https://demo-eoffice.nssf.go.tz:8082/';
    }

    public static function getBmsApi(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        if ($serverIP == '10.10.104.202') {
            return 'https://bcmspro-api.nssf.go.tz/api';
        } elseif ($serverIP == '10.10.47.202') {
            return 'https://bridge-core-dev.nssf.go.tz/api';
        }
        // Default to development API
        return 'https://bridge-core-dev.nssf.go.tz/api';
    }

    /**
     * BMS web portal URL for email CTAs (environment follows server IP, same as getBmsApi).
     * Override on any server with BMS_PORTAL_URL in .env.
     */
    public static function getBmsPortalUrl(): string
    {
        $override = env('BMS_PORTAL_URL');
        if (is_string($override) && $override !== '') {
            return rtrim($override, '/');
        }

        $serverIP = $_SERVER['SERVER_ADDR'] ?? '';
        if ($serverIP === '10.10.104.202') {
            return rtrim((string) config('params.api_urls.bms_portal.production'), '/');
        }

        return rtrim((string) config('params.api_urls.bms_portal.pre_production'), '/');
    }

    public static function getBudgetApi(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        if ($serverIP == '10.10.104.202') {
            return 'https://budget-api.nssf.go.tz';
        } elseif ($serverIP == '10.10.47.202') {
            return 'https://planb-pre.nssf.go.tz/api/web';
        }
        // Default to development API
        return 'https://planb-pre.nssf.go.tz/api/web';
    }


    public static function getFMSApi(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        if ($serverIP == '10.10.104.202') {
            return 'https://fms-api.nssf.go.tz/api';
        } elseif ($serverIP == '10.10.47.202') {
            return 'https://imprestpre-api.nssf.go.tz/api';
        }
    }
}
