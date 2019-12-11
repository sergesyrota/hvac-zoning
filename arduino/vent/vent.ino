#include <SFE_BMP180.h>
#include <Wire.h>
#include <SyrotaAutomation1.h>
#include <EEPROMex.h>
#include <MiniStepper.h>
#include "config.h"

MiniStepper motor = MiniStepper(4096, MOTOR_PINS);
SyrotaAutomation net = SyrotaAutomation(RS485_CONTROL_PIN);
SFE_BMP180 bmp180;
bmp180Data sensor;

// Buffer for char conversions
char buf [100];
// Error handling
bool errorPresent = false;
byte errorNum;
// Tracks when setDegrees was called last
unsigned long lastSetDegreesCommandMillis;
bool atDefaultPosition = false; // set this to true when going to default so we don't do it over and over again

// Values we store in EEPROM
struct configuration_t conf = {
  CONFIG_VERSION,
  // Default values for config
  9600UL, //unsigned long baudRate; // Serial/RS-485 rate: 9600, 14400, 19200, 28800, 38400, 57600, or 115200
  50, //int endstopCorrectionSteps; // Number of steps to take after we hit and endstop to land at fully closed position
  10, //int motorSpeed; // Motor speed in RPM of the final output shaft
  1200, //int positionTimeout; // When this many seconds passes since last command, reset position to default
  60, //int defaultDegrees; // Default degrees to set when timeout since last command is reached
};

void(* resetFunc) (void) = 0; //declare reset function @ address 0

void setup()
{
  readConfig();
  pinMode(ENDSTOP_PIN, INPUT_PULLUP);
  strcpy(net.deviceID, NET_ADDRESS);
  motor.setSpeed(conf.motorSpeed);
  calibrate();
  Serial.begin(conf.baudRate);
}

void readConfig()
{
  // Check to make sure config values are real, by looking at first 3 bytes
  if (EEPROM.read(0) == CONFIG_VERSION[0] &&
    EEPROM.read(1) == CONFIG_VERSION[1] &&
    EEPROM.read(2) == CONFIG_VERSION[2]) {
    EEPROM.readBlock(0, conf);
  } else {
    // Configuration is invalid, so let's write default to memory
    saveConfig();
  }
}

void saveConfig()
{
  EEPROM.writeBlock(0, conf);
}

void loop() 
{
  if (net.messageReceived()) {
    if (net.assertCommandStarts("set", buf)) {
      processSetCommands();
    } else if (net.assertCommand("getSensor")) {
      sprintf(buf, "%d C*100; %lu Pa (%d ms ago); status: %d", 
        (int)(sensor.temperature*100), 
        (unsigned long)(sensor.pressure*100UL), 
        (millis() - sensor.lastSuccessReadTime), 
        sensor.status);
      net.sendResponse(buf);
    } else if (net.assertCommand("getPosition")) {
      if (errorPresent) {
        net.sendResponse("Unknown");
      } else {
        sprintf(buf, "%d", motor.getCurrentPosition());
        net.sendResponse(buf);
      }
    } else if (net.assertCommand("calibrate")) {
      // Since it takes a while, need to send response now
      net.sendResponse("OK");
      calibrate();
    } else if (net.assertCommand("errorPresent")) {
      if (errorPresent) {
        sprintf(buf, "YES: %s", errors[errorNum]);
        net.sendResponse(buf);
      } else {
        net.sendResponse("NO");
      }
    } else if (net.assertCommandStarts("step:", buf)) {
      int tmp = strtol(buf, NULL, 10);
      // Since it might take a while, need to send response now
      net.sendResponse("OK");
      motor.step(tmp);
      motor.stopIdleHold();
    } else if (net.assertCommand("reset")) {
      net.sendResponse("Rebooting");
      // wait a bit for send buffer to clear, or somehting.
      delay(10);
      resetFunc();  // reboot
    } else {
      net.sendResponse("Unrecognized command");
    }
  }

  // Update sensor data
  if (millis() - sensor.lastAttemptReadTime > BMP_SAMPLE_INTERVAL) {
    readSensor();
  }

  // Check if position timeout is reached
  if (!atDefaultPosition && 
    conf.positionTimeout > 0 && 
    ((millis() - lastSetDegreesCommandMillis)/1000) > conf.positionTimeout
  ) {
    goToDegree(conf.defaultDegrees);
    atDefaultPosition = true;
  }

  // Just in case, always stop idle hold, so that we're not overheating the motor (although we should've set this after every move)
  motor.stopIdleHold();
}

void processSetCommands()
{
  if (net.assertCommandStarts("setDegrees:", buf)) {
    int tmp = strtol(buf, NULL, 10);
    if (tmp >= 0 && tmp <= 360) {
      // Response first, as rotation can take a while
      net.sendResponse("Working");
      goToDegree(tmp);
      atDefaultPosition = false;
    } else {
      net.sendResponse("ERROR");
    }
  } else if (net.assertCommandStarts("setMotorSpeed:", buf)) {
    int tmp = strtol(buf, NULL, 10);
    if (tmp > 0 && tmp <= 20) { // 20 RPM showed to be about the limit in tests
      conf.motorSpeed = tmp;
      saveConfig();
      motor.setSpeed(conf.motorSpeed);
      net.sendResponse("OK");
    } else {
      net.sendResponse("ERROR");
    }
  } else if (net.assertCommandStarts("setPositionTimeout:", buf)) {
    int tmp = strtol(buf, NULL, 10);
    if (tmp > 0 && tmp <= 32767) { // Upper limit of int
      conf.positionTimeout = tmp;
      saveConfig();
      net.sendResponse("OK");
    } else {
      net.sendResponse("ERROR");
    }
  } else if (net.assertCommandStarts("setDefaultDegrees:", buf)) {
    int tmp = strtol(buf, NULL, 10);
    if (tmp > 0 && tmp <= 90) { // Doesn't make sense to set default outside of 0-90.
      conf.defaultDegrees = tmp;
      saveConfig();
      net.sendResponse("OK");
    } else {
      net.sendResponse("ERROR");
    }
  } else if (net.assertCommandStarts("setBaudRate:", buf)) {
    long tmp = strtol(buf, NULL, 10);
    // Supported: 9600, 14400, 19200, 28800, 38400, 57600, or 115200
    if (tmp == 9600 ||
       tmp == 14400 ||
       tmp == 19200 ||
       tmp == 28800 ||
       tmp == 38400 ||
       tmp == 57600 ||
       tmp == 115200
    ) {
      conf.baudRate = tmp;
      saveConfig();
      net.sendResponse("OK");
      Serial.end();
      Serial.begin(tmp);
    } else {
      net.sendResponse("ERROR");
    }
  } else if (net.assertCommandStarts("setCorrectionSteps:", buf)) {
    // correction steps can be both positive and negative, not no more than 1k steps, probably
    int tmp = strtol(buf, NULL, 10);
    if (tmp > -1000 && tmp <= 1000) {
      conf.endstopCorrectionSteps = tmp;
      saveConfig();
      net.sendResponse("OK");
    } else {
      net.sendResponse("ERROR");
    }
  } else {
    net.sendResponse("Unrecognized command");
  }
}

void calibrate()
{
  // If endstop is engaged, we need to rotate away from it
  int i = 0;
  while (digitalRead(ENDSTOP_PIN) == LOW) {
    motor.step(-1);
    i++;
    // Stop and raise error if it seems like there is a problem (motor failure, switch failure, stuck damper)
    if (i>5000) {
      raiseError(0);
      motor.stopIdleHold();
      return;
    }
  }
  // If we're clear, this error didn't happen
  clearError(0);
  // Now rotating until we hit an endstop
  i=0;
  while (digitalRead(ENDSTOP_PIN) == HIGH) {
    motor.step(1);
    i++;
    // Stop and raise error if it seems like there is a problem (motor failure, switch failure, stuck damper)
    if (i>5000) {
      raiseError(1);
      motor.stopIdleHold();
      return;
    }
  }
  // If we're here, this error is no longer happening
  clearError(1);
  // And after reaching endstop switch, need to correct for fully closed position
  motor.step(conf.endstopCorrectionSteps);
  motor.setZero();
  // And since we're not sure what we need to do now, we'll fully open the vent.
  goToDegree(90);
}

// Rotates the vent to specific position; Keep in mind that free play in mechanism means it's not very precise
void goToDegree(int deg)
{
  motor.goToDegree(deg);
  motor.stopIdleHold();
  // If we go to 0, need to double check that endstop was triggered, otherwise it indicates malfunction
  if (deg==0) {
    if (digitalRead(ENDSTOP_PIN) == HIGH) {
      raiseError(2);
    } else {
      clearError(2);
    }
  }
}

void raiseError(byte errNo)
{
  errorPresent = true;
  errorNum = errNo;
}

// If current error matches this number, we're considering it gone
void clearError(byte errNo)
{
  if (errNo = errorNum) {
    errorPresent = false;
  }
}

/**
 * Reads pressure and temperature from BMP180 sensor
 */
void readSensor()
{
  sensor.lastAttemptReadTime = millis();
  char status;
  
  if (!bmp180.begin()) {
    sensor.status = 1;
    return;
  }
  // Read temperature
  status = bmp180.startTemperature();
  if (status == 0) {
    sensor.status = 2;
    return;
  }
  delay(status);
  status = bmp180.getTemperature(sensor.temperature);
  if (status == 0) {
    sensor.status = 3;
    return;
  } 
  
  // Read pressure
  status = bmp180.startPressure(3);
  if (status == 0) {
    sensor.status = 4;
    return;
  }
  delay(status);
  status = bmp180.getPressure(sensor.pressure, sensor.temperature);
  if (status == 0) {
    sensor.status = 5;
    return;
  } 
  
  sensor.status = 0;
  sensor.lastSuccessReadTime = millis();
}
