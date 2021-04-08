<?php

namespace ALajusticia\SanctumTracker\Factories;

use ALajusticia\SanctumTracker\Exceptions\CustomIpProviderException;
use ALajusticia\SanctumTracker\Exceptions\IpProviderException;
use ALajusticia\SanctumTracker\Interfaces\IpProvider;
use ALajusticia\SanctumTracker\IpProviders\Ip2LocationLite;
use ALajusticia\SanctumTracker\IpProviders\IpApi;
use Illuminate\Support\Facades\App;

class IpProviderFactory
{
    /**
     * Build a new IP provider.
     *
     * @param string $name
     * @return IpApi|object|void
     * @throws \Exception|\GuzzleHttp\Exception\GuzzleException
     */
    public static function build($name)
    {
        if (self::ipLookupEnabled()) {
            $customProviders = config('sanctum_tracker.ip_lookup.custom_providers');

            if ($customProviders && array_key_exists($name, $customProviders)) {

                // Use of a custom IP address lookup provider

                if (!in_array(IpProvider::class, class_implements($customProviders[$name]))) {

                    // The custom IP provider class doesn't
                    // implement the required interface

                    throw new CustomIpProviderException;
                }

                return new $customProviders[$name];

            } else {

                // Use of an officially supported IP address lookup provider

                switch ($name) {
                    case 'ip2location-lite':
                        return new Ip2LocationLite;
                    case 'ip-api':
                        return new IpApi;
                    default:
                        throw new IpProviderException;
                }
            }
        }
    }

    /**
     * Check if the IP lookup feature is enabled.
     *
     * @return bool
     */
    public static function ipLookupEnabled()
    {
        return config('sanctum_tracker.ip_lookup.provider') &&
            App::environment(config('sanctum_tracker.ip_lookup.environments'));
    }
}
