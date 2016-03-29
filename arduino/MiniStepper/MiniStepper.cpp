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
	pinMode(bluePin, OUTPUT);
	pinMode(yellowPin, OUTPUT);
	pinMode(pinkPin, OUTPUT);
	pinMode(orangePin, OUTPUT);
}

// Signifies that current position of the motor is at 0 degrees.
// goToDegree uses this calibration to go to a known location.
void MiniStepper::setZero()
{
	currentPosition=0;
}

void MiniStepper::step(long steps)
{
	startIdleHold(); // engage all pins at this stage, so we can do incremental updates
	long absSteps = abs(steps);
	int stepDirection = (steps<0 ? -1 : 1);
	unsigned long startTime;
	unsigned long sleepTime;
	for (long i=0; i<absSteps; i++) {
		startTime = micros();
		incrementStep(stepDirection);
		if (stepDirection > 0) {
			executePositiveStep();
		} else {
			executeNegativeStep();
		}
		delayMicroseconds(stepMicros - (startTime - micros())); // Always doing subtraction calculations to make sure we don't have weird rollover incidents.
	}
	
}

void MiniStepper::incrementStep(int stepDirection)
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

// Idea here is that we only make changes needed at this specific step, leaving all other pins intact. 
// This way we won't have a moment when some wrong combination of pins is enagaged.
void MiniStepper::executePositiveStep()
{
	switch(currentStep) {
		case 0:
			digitalWrite(bluePin, LOW);
			break;
		case 1:
			digitalWrite(yellowPin, HIGH);
			break;
		case 2:
			digitalWrite(orangePin, LOW);
			break;
		case 3:
			digitalWrite(pinkPin, HIGH);
			break;
		case 4:
			digitalWrite(yellowPin, LOW);
			break;
		case 5:
			digitalWrite(bluePin, HIGH);
			break;
		case 6:
			digitalWrite(pinkPin, LOW);
			break;
		case 7:
			digitalWrite(orangePin, HIGH);
			break;
	}
}
void MiniStepper::executeNegativeStep()
{
	switch(currentStep) {
		case 0:
			digitalWrite(yellowPin, LOW);
			break;
		case 1:
			digitalWrite(orangePin, HIGH);
			break;
		case 2:
			digitalWrite(pinkPin, LOW);
			break;
		case 3:
			digitalWrite(yellowPin, HIGH);
			break;
		case 4:
			digitalWrite(bluePin, LOW);
			break;
		case 5:
			digitalWrite(pinkPin, HIGH);
			break;
		case 6:
			digitalWrite(orangePin, LOW);
			break;
		case 7:
			digitalWrite(bluePin, HIGH);
			break;
	}
}

void MiniStepper::startIdleHold()
{
	switch (currentStep) {
		case 0:
		case 1:
		case 7:
			digitalWrite(orangePin, HIGH);
			break;
		default:
			digitalWrite(orangePin, LOW);
			break;
	}
	switch (currentStep) {
		case 1:
		case 2:
		case 3:
			digitalWrite(yellowPin, HIGH);
			break;
		default:
			digitalWrite(yellowPin, LOW);
			break;
	}
	switch (currentStep) {
		case 3:
		case 4:
		case 5:
			digitalWrite(pinkPin, HIGH);
			break;
		default:
			digitalWrite(pinkPin, LOW);
			break;
	}
	switch (currentStep) {
		case 5:
		case 6:
		case 7:
			digitalWrite(bluePin, HIGH);
			break;
		default:
			digitalWrite(bluePin, LOW);
			break;
	}
}

void MiniStepper::stopIdleHold()
{
	digitalWrite(orangePin, LOW);
	digitalWrite(yellowPin, LOW);
	digitalWrite(pinkPin, LOW);
	digitalWrite(bluePin, LOW);
}

void MiniStepper::setSpeed(unsigned int targetRpm)
{
	if (targetRpm>MINI_STEPPER_MAX_RPM) {
		targetRpm=MINI_STEPPER_MAX_RPM;
	}
	rpm = targetRpm;
	stepMicros = (60000000UL/(targetRpm*4096UL));
}

void MiniStepper::goToDegree(int degrees)
{
	int targetPosition = (long)degrees * stepsPerRevolution/360; // Cast into long to avoid integer overflow problems with in the multiplication part of the formula.
	int stepsToTake = abs(targetPosition-currentPosition);
	int direction = 0;
	if (targetPosition < currentPosition) {
		direction = -1;
	} else {
		direction = 1;
	}
	// Instead of running in the same direction, may be it's faster to go in reverse
	if (stepsToTake > stepsPerRevolution/2) {
		stepsToTake = stepsPerRevolution-stepsToTake;
		direction = direction * -1;
	}
	return step(direction*stepsToTake);
}