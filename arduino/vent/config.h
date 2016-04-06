#define NET_ADDRESS "MasterVent1"
#define CONFIG_VERSION "VN2"

#define RS485_CONTROL_PIN 2
#define ENDSTOP_PIN 3
#define MOTOR_PINS 4,6,5,7
#define BMP_SAMPLE_INTERVAL 2000

const char errors[3][30] = { 
  "Calibrate: disengage endstop", // 0
  "Calibrate: engage endstop", // 1
  "0 airflow no endstop" // 2
};
//"............................." < 29 chars + \0

struct configuration_t {
  char checkVersion[4]; // This is for detection if we have right settings or not
  unsigned long baudRate; // Serial/RS-485 rate: 9600, 14400, 19200, 28800, 38400, 57600, or 115200
  int endstopCorrectionSteps; // Number of steps to take after we hit and endstop to land at fully closed position
  int motorSpeed; // Motor speed in RPM of the final output shaft
};

struct bmp180Data {
  double temperature;
  double pressure;
  // 0: OK
  // 1: begin() failed
  // 2: cannot start temperature
  // 3: cannot read temperature
  // 4: cannot start pressure
  // 5: cannot read pressuve
  int status;
  unsigned long lastSuccessReadTime;
  unsigned long lastAttemptReadTime;
};
