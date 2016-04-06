

#ifndef MiniStepper_h
#define MiniStepper_h

#include <Arduino.h>

#define MINI_STEPPER_DEFAULT_RPM 10
#define MINI_STEPPER_STEPS_PER_REV 4096
// With 30 micros per setep, looks like maximum theoretical RPM is ~488.
#define MINI_STEPPER_MAX_RPM 488

class MiniStepper
{
	public:
		MiniStepper(unsigned int steps, byte blue, byte yellow, byte pink, byte orange); // Still including steps, and weird wire arrangement, to be compatible with Arduino Stepper library. Pay attention to the order of colors!
		void setZero(); // Sets current position as 0 (e.g. when calibrating)
		void step(long steps); // Make a number of steps CW (>0) or CCW (<0); This function is blocking, meaning it will run until all steps are done, without releasing control.
		void setSpeed(unsigned int targetRpm); // RPM
		void goToDegree(int degrees); // go to specific position, shown in degrees. Needs to be calibrated with setZero(), otherwise it assumes 0 degrees is whatever position it was in when instantiated.
		int getCurrentPosition(); // Returns current position in degrees, from 0 to 359.
		void stopIdleHold(); // Disables motor pins (writes LOW to all), so that power is not consumed.
		void startIdleHold(); // turns on needed outputs to hold motor in current position
	private:
		void setStep(byte stepNum); // Engages pins for the current step, according to the table at the top of the cpp file
		void incrementStep(int stepDirection);
		void executeNegativeStep();
		void executePositiveStep();
		int currentPosition; // N out of 4,096 (steps per revolution)
		int currentStep; // Still need this, as currentPosition of 0 might not be at step 0; and int, because byte is unsigned...
		byte bluePin;
		byte yellowPin;
		byte pinkPin;
		byte orangePin;
		unsigned int rpm;
		unsigned int stepsPerRevolution;
		unsigned long stepMicros;
};

#endif