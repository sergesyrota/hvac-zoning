<?php

namespace Thermostat;

class Factory {
    public static function get($config) {
        switch ($config->type) {
            case 'cli':
                return new \Thermostat\Cli($config->connection);
            case 'nest':
                $cacheFile = null;
                if (!empty(getenv('NEST_CACHE_FILE_PREFIX'))) {
                    $cacheFile = getenv('NEST_CACHE_FILE_PREFIX') . md5(getenv($config->connection->bearer_token));
                }
                $connector = new Connector\Nest(
                    getenv($config->connection->bearer_token),
                    getenv($config->connection->device_id),
                    $cacheFile
                );
                $threshold = (isset($config->threshold) ? $config->threshold : 0);
                return new Nest($connector, $threshold);
            case 'homebridge':
                $connector = new Connector\Homebridge(
                    getenv('HOMEBRIDGE_BASEURL'),
                    getenv($config->connection->username),
                    getenv($config->connection->password),
                    getenv($config->connection->device_id)
                );
                $threshold = (isset($config->threshold) ? $config->threshold : 0);
                return new Homebridge($connector, $threshold);
            default:
                throw new \Exception("Unsupported thermostat type: " . $config->type);
        }
    }
}
