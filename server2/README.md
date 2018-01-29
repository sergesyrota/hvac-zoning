# V2 implementation of simple cron server

This is a pretty basic implementation that relies on having other interfaces implemented separately. It does not do scheduling, it does not do overrides, or any other types of decision about the room. It gets calls for heat/cool for different zones and operates all vents associated with the zone accordingly.

## Configuration

JSON file is used for configuration. Each zone should have a unique ID, one thermostat of supported type, and any number of supported vents (including 0).

Sample configuration for 1 zone:

    "1": {
        "name": "Middle Bedroom",
        "master": true,
        "thermostat": {
            "type": "cli",
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
        ]
    }

General application configuration parameters are set in environment variables. See .env file.

### Supported thermostat adapters

See adapters/thermostat/factory.php

 * cli: run a shell command to obtain thermostat data

### Supported vent adapters

See adapters/vent/factory.php

 * RS485v1: https://github.com/sergesyrota/hvac-zoning/tree/master/arduino/vent

## Running the app

Easiest way to run the app:

    env - $(cat ./.env) php ./cron.php
