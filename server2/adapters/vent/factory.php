<?php

class VentFactory {
    public static function get($config) {
        switch ($config->type) {
            case 'rs485v1':
                if (empty(getenv('RS485V1_TASK')) || empty(getenv('RS485V1_HOST'))) {
                    throw new \Exception('Both RS485V1_HOST and RS485V1_TASK are required for rs485v1 adapter to operate.');
                }
                $gm = new \SyrotaAutomation\Gearman(getenv('RS485V1_TASK'), getenv('RS485V1_HOST'));
                return new \Vent\RS485v1($config->connection, $gm);
            default:
                throw new Exception("Unsupported vent type: " . $config->type);
        }
    }
}
