<?php

namespace Thermostat;

interface iThermostat {

    const MODE_AUTO = 'auto';
    const MODE_HEAT = 'heat';
    const MODE_COOL = 'cool';
    const MODE_OFF = 'off';

    // Gets thermostat's current operating mode:
    // - auto
    // - heat
    // - cool
    // - off
    public function getMode();

    // Gets thermostat's call:
    // - heat
    // - cool
    // - off
    public function getCall();

    // Adds logging ability; Can be ignored if not supported
    public function setLogger();
}
