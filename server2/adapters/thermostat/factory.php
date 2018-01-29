<?php

class ThermostatFactory {
    public static function get($config) {
        switch ($config->type) {
            case 'cli':
                return new \Thermostat\Cli($config->connection);
            default:
                throw new Exception("Unsupported thermostat type: " . $config->type);
        }
    }
}
