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
                    $cacheFile = getenv('NEST_CACHE_FILE_PREFIX') . md5($config->connection->bearer_token);
                }
                $connector = new Connector\Nest(
                    getenv($config->connection->bearer_token),
                    getenv($config->connection->device_id),
                    $cacheFile
                );
                return new Nest($connector, $config->threshold);
            default:
                throw new \Exception("Unsupported thermostat type: " . $config->type);
        }
    }
}
