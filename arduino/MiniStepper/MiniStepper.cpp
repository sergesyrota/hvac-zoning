#include "MiniStepper.h"

/*

motor: 28BYJ48-*
  steps: 8
  stride angle: 5.625/64

  steps per 1 revolution: 360/(5.625/64) = 4,096

            | 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 |
  4 orange  | x | x |   |   |   |   |   | x |
  ----------|---|---|---|---|---|---|---|---|
  3 yellow  |   | x | x | x |   |   |   |   |
  ----------|---|---|---|---|---|---|---|---|
  2 pink    |   |   |   | x | x | x |   |   |
  ----------|---|---|---|---|---|---|---|---|
  1 blue    |   |   |   |   |   | x | x | x |
  ----------|---|---|---|---|---|---|---|---|

*/

MiniStepper::MiniStepper(unsigned int steps, byte blue, byte yellow, byte pink, byte orange)
{
	// Ignoring steps, as it's kept for backward-compatibility only;
	bluePin=blue;
	yellowPin=yellow;
	pinkPin=pink;
	orangePin=orange;
	setSpeed(MINI_STEPPER_DEFAULT_RPM); // Default RPM
	stepsPerRevolution=MINI_STEPPER_STEPS_PER_REV; // This is specific to this motor, so hardcoding steps
}

void MiniStepper::setZero()
{
	currentPosition=0;
}

void MiniStepper::step(long steps)
{
	long absSteps = abs(steps);
	byte stepDirection = (steps<0 ? -1 : 1);
	for (long i=0; i<absSteps; i+=stepDirection) {
		incrementStep(stepDirection);
		executeStep();
	}
}

void MiniStepper::incrementStep(byte stepDirection)
{
	currentStep+=stepDirection;
	currentPosition+=stepDirection;
	// wrap when reaching end or begining of the step cycle
	if (currentStep>7) {
		currentStep=0;
	} else if (currentStep<0) {
		currentStep=7;
	}
	// wrap when reaching end or beginning of total revolution cycle
	if (currentPosition>=MINI_STEPPER_STEPS_PER_REV) {
		currentPosition=0;
	} else if (currentPosition<0) {
		currentPosition=MINI_STEPPER_STEPS_PER_REV-1;
	}
}

void MiniStepper::executeStep()
{
	// TODO
}

void MiniStepper::setSpeed(unsigned int targetRpm)
{
	if (targetRpm>MINI_STEPPER_MAX_RPM) {
		targetRpm=MINI_STEPPER_MAX_RPM;
	}
	rpm = targetRpm;
	stepDelayMicros = (60000000UL/(targetRpm*4096UL)) - 8; // 2 microseconds per digital write, 4 pins to write, so delay should be 8us less than calculated
}

class MiniStepper
{
	public:
		MiniStepper(unsigned int steps, byte blue, byte yellow, byte pink, byte orange); // Still including steps, and weird wire arrangement, to be compatible with Arduino Stepper library. Pay attention to the order of colors!
		void setZero(); // Sets current position as 0 (e.g. when calibrating)
		void step(long steps); // Make a number of steps CW (>0) or CCW (<0); This function is blocking, meaning it will run until all steps are done, without releasing control.
		bool setSpeed(); // RPM
		void goToDegree(); // go to specific position, shown in degrees. Needs to be calibrated with setZero(), otherwise it assumes 0 degrees is whatever position it was in when instantiated.
		void stopIdleHold(); // Disables motor pins (writes LOW to all), so that power is not consumed.
	private:
		void setStep(byte stepNum);
		void incrementStep(byte stepDirection));
		int currentPosition; // N out of 4,096 (steps per revolution)
		byte currentStep; // Still need this, as 0 might not be at the first step
		byte bluePin;
		byte yellowPin;
		byte pinkPin;
		byte orangePin;
		unsigned int rpm;
		unsigned long stepDelayMicros; // 60,000,000/rpm
		unsigned int stepsPerRevolution;
}