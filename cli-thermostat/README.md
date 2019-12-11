# CLI thermostat

Hacked together quick implementation of a thermostat that can work off of schedule in the JSON file, and only 1 pluggable piece being a function to get current temperature getCurrentTemp();

Environment variables need to be defined to configure the thermostat

 * TIMEZONE: Timezone identifier (e.g. America/Chicago)
 * TEMP_TOLERANCE: number of degrees +/- for hysteresis
 * SCHEDULE_FILE: relative to thermostat PHP file, JSON data containing schedule
 * STATE_FILE: temporary file where state should be saved between runs (needed for hysteresis)
 * GET_CURRENT_TEMP_FUNC_FILE: include file that needs to provide getCurrentTemp() function
