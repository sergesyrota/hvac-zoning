<?php

class ThermostatFactory {
    public static function get($config) {
        switch ($config->type) {
            case 'cli':
                return new \Thermostat\Cli($config->connection);
            case 'nest':
                return new \Thermostat\Nest($config->connection);
            default:
                throw new Exception("Unsupported thermostat type: " . $config->type);
        }
    }
}
