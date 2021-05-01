# V2 implementation of simple cron server

This is a pretty basic implementation that relies on having other interfaces implemented separately. It does not do scheduling, it does not do overrides, or any other types of decision about the room. It gets calls for heat/cool for different zones and operates all vents associated with the zone accordingly.

## Configuration

JSON file is used for configuration. Each zone should have a unique ID, one thermostat of supported type, and any number of supported vents (including 0). Zones should be put into "zones" object. General parameters should be put into "parameters".

Supported parameters:

 * min_airflow: minimum airflow units the system should have open at any given time. Arbitrary unit of measure, just has to be consistent with airflow parameter for each zone. When desired vent state leads to lower than minAirflow, we first try to open up non-master zones, then master zone to reach desired minAirflow parameter.
 * state_expiration: Number of seconds since last state update to consider saved state no longer valid (e.g. when some parts of the system are not working, need to discard everything we thought we knew about the system)
 * override_activate_time: number of seconds system needs to be working without interruption before enabling master zone override functionality. This need to be long enough for the schedule to reset temperatures, if some override was applied and not reset.

Sample configuration for 1 zone:

    "1": {
        "name": "Middle Bedroom",
        "master": true,
        "thermostat": {
            "type": "cli",
            "threshold": 2,
            "connection": {
                "command": "echo '{}'"
            }
        },
        "vents": [
            {
                "type": "rs485v1",
                "connection": {
                    "device": "name"
                }
            }
        ],
        "airflow": 4
    }

Zone configuration parameters:

 * master: needs to be true for the zone that has a thermostat controlling equipment. Exactly one master zone is required for a valid configuration
 * thermostat.threshold: number of degrees a thermostat can be off, when we're considering applying master override.
 * airflow: arbitrary units of airflow the zone provides when fully opened. Used in calculation of min_airflow in overall system parameters.

General application configuration parameters are set in environment variables. See .env.example file.

### Supported thermostat adapters

See adapters/thermostat/factory.php

 * cli: run a shell command to obtain thermostat data
 * NEST: query the API to get status information. Connection information has environment variable names, rather than sensitive data.

### Supported vent adapters

See adapters/vent/factory.php

 * RS485v1: https://github.com/sergesyrota/hvac-zoning/tree/master/arduino/vent

## Running the app

Easiest way to run the app:

    env - $(cat ./.env) php ./cron.php

# TODO

* When `last_connection` exceeds a certain threshold - fall back into "fail safe" mode
* When room temperature doesn't change for significant period of time after starting the system - fall back into "fail safe" mode
* Implement periodic calibration
* Implement extra fail safe when temp probe on exit has an extreme reading
