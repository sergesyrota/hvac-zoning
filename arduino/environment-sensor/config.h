// Debug mode. Comment out to disable.
//#define DEBUG

// Config version
#define CONFIG_VERSION "EV1"

// SyrotaAutomation parameters
#define RS485_CONTROL_PIN 2
#define NET_ADDRESS "EnvMaster"

#define DHT22_PIN 10
#define DHT_UPDATE_INTERVAL 5000

struct configuration_t {
  char checkVersion[4]; // This is for detection if we have right settings or not
  unsigned long baudRate; // Serial/RS-485 rate: 9600, 14400, 19200, 28800, 38400, 57600, or 
};

struct dht_t {
  unsigned long lastAttemptTime;
  unsigned long lastSuccessTime;
  float temperature;
  float humidity;
};


