<?php

namespace Thermostat;

class Nest implements iThermostat {
    // Connector class for interfacing with API
    private $con;
    // Threshold
    private $threshold = 0;

    /**
     * NEST thermostat adapter
     *
     * @param \Thermostat\Connector\Nest $connector Instance of a connector for communication with the API
     * @param int $threshold Temperature threshold by which it's OK to overshot the target (needed for master zone only)
     */
    public function __construct(Connector\Nest $connector, $threshold = 0) {
        $this->con = $connector;
        $this->threshold = $threshold;
    }

    /**
     * Gets current thermostat mode (e.g. heat, cool, etc). See constants in iThermostat
     */
    public function getMode() {
        $data = $this->con->getData();
        if (empty($data->hvac_mode)) {
            throw new \Exception("Unknown mode for NEST thermostat {$this->thermostatId}");
        }
        switch ($data->hvac_mode) {
            case 'heat-cool':
            case 'eco':
                return iThermostat::MODE_AUTO;
            case 'heat':
                return iThermostat::MODE_HEAT;
            case 'cool':
                return iThermostat::MODE_COOL;
            case 'off':
                return iThermostat::MODE_OFF;
            default:
                throw new \Exception("Unknown thermostat mode in NEST adapter: {$data->hvac_mode}");
        }
    }

    /**
     * Get what the thermostat is currently actively doing, if anything. Can be heat, cool, or off. See iThermostat constants.
     */
    public function getCall() {
        $data = $this->con->getData();
        if (empty($data->hvac_state)) {
            throw new \Exception("Empty call for NEST thermostat {$this->thermostatId}");
        }
        switch ($data->hvac_state) {
            case 'heating':
                return iThermostat::MODE_HEAT;
            case 'cooling':
                return iThermostat::MODE_COOL;
            case 'off':
                return iThermostat::MODE_OFF;
            default:
                throw new \Exception("Unknown thermostat call in NEST adapter: {$data->hvac_state}");
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
        $string = $data->hvac_mode;
        switch ($data->hvac_mode) {
            case 'heat-cool':
                $string .= (string)$data->target_temperature_low_f;
                $string .= (string)$data->target_temperature_high_f;
                break;
            case 'eco':
                $string .= (string)$data->eco_temperature_low_f;
                $string .= (string)$data->eco_temperature_high_f;
                break;
            case 'cool': // intentionally collapsing to same as "heat"
            case 'heat':
                $string .= (string)$data->target_temperature_f;
                break;
        }
        return md5($string);
    }

    /**
     * Moves target temperature by "threshold" degrees.
     * This is used when we need to affect other zones, potentially sacrificing this one.
     */
    public function setOverride() {
        $data = $this->con->getData();
        $newTarget = $data->target_temperature_f;
        switch ($data->hvac_mode) {
            case 'cool':
                $newTarget -= $this->threshold;
                break;
            case 'heat':
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
        $newTarget = $data->target_temperature_f;
        switch ($data->hvac_mode) {
            case 'cool':
                $newTarget += $this->threshold;
                break;
            case 'heat':
                $newTarget -= $this->threshold;
                break;
            default:
                throw new \Exception("Overrides are not supported in modes other than heat or cool");
        }
        $this->con->setTarget($newTarget);
    }

    /**
     * Temperature difference between current and target
     * Positive: we've overshot our target
     * Negative: we need to work toward target
     * Automatically adjusts to different modes, so sign can always be used as an indication of desired system state
     *
     * @param string $unit Temperature unit of measure. Can be C or F.
     */
    public function deltaT($unit='F') {
        $data = $this->con->getData();
        // Need to translate to heat or cool, depending where we're at
        $delta = 0;
        switch ($data->hvac_mode) {
            case 'heat-cool':
                $delta = $this->getAutoDelta($data, $data->target_temperature_low_f, $data->target_temperature_high_f);
                break;
            case 'eco':
                $delta = $this->getAutoDelta($data, $data->eco_temperature_low_f, $data->eco_temperature_high_f);
                break;
            case 'cool':
                $delta = $data->target_temperature_f - $data->ambient_temperature_f;
                break;
            case 'heat':
                $delta = $data->ambient_temperature_f - $data->target_temperature_f;
                break;
            case 'off':
                $delta = 0;
                break;
            default:
                throw new \Exception("Unknown thermostat mode in NEST adapter: {$data->hvac_mode}");
        }
        if ($unit = 'C') {
            return round(($delta - 32) / 1.8, 1);
        }
        return $delta;
    }

    /**
     * When mode is one of automatic ones, figure out which target is closer, and infer what we need to work toward.
     * Heat and cool target are needed as we need to make a decision which variable to use depending on mode.
     *
     * @param object $data Nest thermostat data
     * @param int $heatTarget Current actual heat target of auto mode (in F)
     * @param int $coolTarget Current actual cool target of auto mode (in F)
     */
    private function getAutoDelta($data, $heatTarget, $coolTarget) {
        $t = $data->ambient_temperature_f;
        if (abs($t-$heatTarget) < abs($t-$coolTarget)) {
            // Heat mode
            return $t - $heatTarget;
        } else {
            // Cool mode
            return $coolTarget - $t;
        }
        throw new Exception('Cannot figure out AutoDelta.');
    }

    // Not supported
    public function setLogger($logger) {

    }
}
