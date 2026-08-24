<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Mockery\Exception;

class EnvironmentHelper extends Model
{
   
    public static function GePGBaseUrl(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
        $productionIP = config('params.server_ips.production');
        $preProductionIP = config('params.server_ips.pre_production');
        
        if ($serverIP == $productionIP) {
            return config('params.api_urls.gepg_base.production');
        } elseif ($serverIP == $preProductionIP) {
            return config('params.api_urls.gepg_base.development');
        }

        return config('params.api_urls.gepg_base.development');
    }

    public static function getEOfficeLink(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
        $productionAltIP = config('params.server_ips.production_alt');
        $developmentIP = config('params.server_ips.development');
        
        if ($serverIP == $productionAltIP) {
            return config('params.api_urls.eoffice.production');
        } elseif ($serverIP == $developmentIP) {
            return config('params.api_urls.eoffice.development');
        }

        return config('params.api_urls.eoffice.development');
    }

    public static function getGePGLink(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
        $productionIP = config('params.server_ips.production');
        $preProductionIP = config('params.server_ips.pre_production');
        
        if ($serverIP == $productionIP) {
            return config('params.api_urls.gepg.production');
        } elseif ($serverIP == $preProductionIP) {
            return config('params.api_urls.gepg.development');
        }

        return config('params.api_urls.gepg.development');
    }

    public static function getNSSFPortalLink(): string
    {
        try {
            $serverIP = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
            $productionIP = config('params.server_ips.production');
            $preProductionIP = config('params.server_ips.pre_production');
            
            if ($serverIP == $productionIP) {
                return config('params.api_urls.nssf_portal.production'); // added # as per change of nssf portal router
            } elseif ($serverIP == $preProductionIP) {
                return config('params.api_urls.nssf_portal.pre_production');
            }
            return config('params.api_urls.nssf_portal.pre_production');
        } catch (Exception $e) {
            return config('params.api_urls.nssf_portal.pre_production');
        }
    }

 

    public static function getHrpDbLink(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
        $productionIP = config('params.server_ips.production');
        $preProductionIP = config('params.server_ips.pre_production');
        
        if ($serverIP == $productionIP) {
            return config('params.api_urls.hrp_db.production');
        } elseif ($serverIP == $preProductionIP) {
            return config('params.api_urls.hrp_db.pre_production');
        }
        return config('params.api_urls.hrp_db.pre_production');
    }

 

    public static function getSubSpCode($payer_type): string
    {
        // config('params.paths.sub_sp_code')
        if ($payer_type == 'Employer ST') {
            return '1002';
        } elseif ($payer_type == 'Member') {
            return '1001';
        } else {
            return '1001';
        }
    }

    public static function getGfsCode($payer_type)
    {
        if ($payer_type == 'Employer ST') {
            return '121201010001';
        } elseif ($payer_type == 'Member') {
            return config('params.paths.gfsCode');
        } else {
            return config('params.paths.gfsCode');
        }
    }

    public static function getSystemId($payer_type)
    {
        if ($payer_type == 'Employer ST') {
            return config('params.paths.system_id');
        } elseif ($payer_type == 'Member') {
            return config('params.paths.system_id');
        } else {
            return config('params.paths.system_id');
        }
    }

    public static function getBillIdPrefix($payer_type): string
    {
        if ($payer_type == 'Employer ST') {
            return 'ST';
        } elseif ($payer_type == 'Employer DAS') {
            return 'DAS';
        } elseif ($payer_type == 'Employer Vol') {
            return 'EVO';
        } elseif ($payer_type == 'Member') {
            return 'NISS';
        } elseif ($payer_type == 'Member Card') {
            return 'MCRD';
        } elseif ($payer_type == 'Group') {
            return 'GRP';
        } elseif ($payer_type == 'Imprest') {
            return 'IMP';
        } elseif ($payer_type == 'Plot') {
            return 'PLT';
        } elseif ($payer_type == 'Self Employer') {
            return 'SLFE';
        } elseif ($payer_type == 'Staff Loan') {
            return 'SFLN';
        } elseif ($payer_type == 'Tenant') {
            return 'TNNT';
        } elseif ($payer_type == 'Vehicle') {
            return 'VHCL';
        } else {
            return 'OT';
        }
    }

    public static function getPaymentOption($payer_type): string
    {
        if ($payer_type == 'Employer ST') {
            return '3';
        } elseif ($payer_type == 'Member' || $payer_type == 'Group' || $payer_type == 'Employer DAS' || $payer_type == 'Employer Vol' || $payer_type == 'Plot') {
            return '5';
            /** Previous was 2 but We replace with 5 since for is limited which meanse it cann not exceed billed amount */
        } else {
            return '3';
        }
    }

    /**
     * Get current environment based on server IP
     */
    public static function getCurrentEnvironment(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
        $productionAltIP = config('params.server_ips.production_alt');
        $developmentIP = config('params.server_ips.development');
        
        if ($serverIP == $productionAltIP) {
            return 'production';
        } elseif ($serverIP == $developmentIP) {
            return 'preproduction';
        }
        return 'development';
    }

    /**
     * Check if current environment is production
     */
    public static function isProduction(): bool
    {
        return self::getCurrentEnvironment() === 'production';
    }

    /**
     * Check if current environment is preproduction
     */
    public static function isPreProduction(): bool
    {
        return self::getCurrentEnvironment() === 'preproduction';
    }

    /**
     * Check if current environment is development
     */
    public static function isDevelopment(): bool
    {
        return self::getCurrentEnvironment() === 'development';
    }
} 