<?php

class VentFactory {
    public static function get($config) {
        switch ($config->type) {
            case 'rs485v1':
                return new \Vent\RS485v1($config->connection);
            default:
                throw new Exception("Unsupported vent type: " . $config->type);
        }
    }
}
