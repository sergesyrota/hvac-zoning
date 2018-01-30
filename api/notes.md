Data structures

Schedule

Json object for schedule per room
Key: schedule-{room-id}
  {
    "Sun": [
      {minute-since-midnight}: {
        "heat": {temp},
        "cool": {temp}
      },
      ...
    ],
    ...
    "Overrides": [
      {
        "start": {timestamp},
        "end": {timestamp},
        "heat": {temp},
        "cool": {temp}
      },
      ...
    ]
  }

Status

Json object for each of the devices on the network
Key: status-room-{room-name}, status-vent-{vent-name}

App structure

Configuration has this:
 - Room id
 - Thermostat, which can be dumb (sensor only) or an actual thermostat
 - Multiple vents
Then current status that's taken from thermostat needs to be compared to schedule, and vents need to be operated.

[
    1: {
        "type": "RS485",
        "connection": {
            "deviceName": "EnvMaster",
            "command": "getDht"
        },
    },
    2: {
        "type": "RadioThermostat",
        "connection": {
            "hostname": "192.168.8.90"
        }
    },
    3: {
        "type": "JSON",
        "connection": {
            "uri": "http://guest-thermostat.iot.syrota.com:5000/"
        }
    }
]
