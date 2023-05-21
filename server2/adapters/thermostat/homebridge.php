<?php

namespace Thermostat;

class Homebridge implements iThermostat {
    // Connector class for interfacing with API
    private $con;
    // Threshold
    private $threshold = 0;

    /**
     * NEST thermostat adapter
     *
     * @param \Thermostat\Connector\Homebridge $connector Instance of a connector for communication with the API
     * @param int $threshold Temperature threshold by which it's OK to overshot the target (needed for master zone only)
     */
    public function __construct(Connector\Homebridge $connector, $threshold = 0) {
        $this->con = $connector;
        $this->threshold = $threshold;
    }

    /**
     * Gets current thermostat mode (e.g. heat, cool, etc). See constants in iThermostat
     */
    public function getMode() {
        $data = $this->con->getData();
        if (!isset($data['serviceCharacteristicsByType']['TargetHeatingCoolingState']['value'])) {
            throw new \Exception("Unknown mode for Homebridge thermostat {$this->thermostatId}");
        }
        $modeValue = $data['serviceCharacteristicsByType']['TargetHeatingCoolingState']['value'];
        switch ($modeValue) {
            case 0:
                return iThermostat::MODE_OFF;
            case 1:
                return iThermostat::MODE_HEAT;
            case 2:
                return iThermostat::MODE_COOL;
            case 3:
                return iThermostat::MODE_AUTO;
            default:
                throw new \Exception("Unknown thermostat mode in Homebridge adapter: {$modeValue}");
        }
    }

    /**
     * Get what the thermostat is currently actively doing, if anything. Can be heat, cool, or off. See iThermostat constants.
     */
    public function getCall() {
        $data = $this->con->getData();
        if (!isset($data['serviceCharacteristicsByType']['CurrentHeatingCoolingState']['value'])) {
            throw new \Exception("Target state value not defined for Homebridge thermostat {$this->thermostatId}");
        }
        $currentState = $data['serviceCharacteristicsByType']['CurrentHeatingCoolingState']['value'];
        switch ($currentState) {
            case 0:
              return iThermostat::MODE_OFF;
            case 1:
                return iThermostat::MODE_HEAT;
            case 2:
                return iThermostat::MODE_COOL;
            default:
                throw new \Exception("Unknown thermostat call in Homebridge adapter: {$currentState}");
        }
    }

    /**
     * Current state checksum, to keep track of external changes. This checksum needs to stay constant while
     * no changes to set temperature are being made. That includes changes that switch mode of the system.
     * It is used for tracking of override application. If checksum is changed, we are assuming that target
     * temperature has been set to exactly what it should be, and we need to discard our override state.
     */
    public function getChecksum() {
        $data = $this->con->getData();
        $string = $data['serviceCharacteristicsByType']['TargetHeatingCoolingState']['value'];
        $string .= $data['serviceCharacteristicsByType']['TargetTemperature']['value'];
        return md5($string);
    }

    /**
     * Moves target temperature by "threshold" degrees.
     * This is used when we need to affect other zones, potentially sacrificing this one.
     */
    public function setOverride() {
        $data = $this->con->getData();
        if ($data['serviceCharacteristicsByType']['TargetTemperature']['unit'] != 'celsius') {
          throw new \Exception('Expecting homebridge to have temperature target in C, but got: ' . $data['serviceCharacteristicsByType']['TargetTemperature']['unit']);
        }
        $newTarget = $this->CtoF($data['serviceCharacteristicsByType']['TargetTemperature']['value']);
        switch ($this->getMode()) {
            case iThermostat::MODE_COOL:
                $newTarget -= $this->threshold;
                break;
            case iThermostat::MODE_HEAT:
                $newTarget += $this->threshold;
                break;
            default:
                throw new \Exception("Overrides are not supported in modes other than heat or cool");
        }
        $this->con->setTarget($newTarget);
    }

    /**
     * Removes override set in "setOverride" function.
     * This function does not care about the state, it just sts the temperature target back by "threshold" degrees.
     * Method that calls this function needs to ensure that it's only called when we're sure no external overrides
     * have been set, to avoid setting temperature back too far.
     */
    public function removeOverride() {
        $data = $this->con->getData();
        if ($data['serviceCharacteristicsByType']['TargetTemperature']['unit'] != 'celsius') {
          throw new \Exception('Expecting homebridge to have temperature target in C, but got: ' . $data['serviceCharacteristicsByType']['TargetTemperature']['unit']);
        }
        $newTarget = $this->CtoF($data['serviceCharacteristicsByType']['TargetTemperature']['value']);
        switch ($this->getMode()) {
            case iThermostat::MODE_COOL:
                $newTarget += $this->threshold;
                break;
            case iThermostat::MODE_HEAT:
                $newTarget -= $this->threshold;
                break;
            default:
                throw new \Exception("Overrides are not supported in modes other than heat or cool");
        }
        $this->con->setTarget($newTarget);
    }

    private function CtoF($C) {
      return ($C * 9/5) + 32;
    }

    private function FtoC($F) {
      return ($F - 32) * 5/9;
    }
}
