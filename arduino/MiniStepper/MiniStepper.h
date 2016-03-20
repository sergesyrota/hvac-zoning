/*

motor: 28BYJ48-*
  steps: 8
  stride angle: 5.625/64

  steps per 1 revolution: 360/(5.625/64) = 4,096

            | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 |
  4 orange  | x | x |   |   |   |   |   | x |
  ----------|---|---|---|---|---|---|---|---|
  3 yellow  |   | x | x | x |   |   |   |   |
  ----------|---|---|---|---|---|---|---|---|
  2 pink    |   |   |   | x | x | x |   |   |
  ----------|---|---|---|---|---|---|---|---|
  1 blue    |   |   |   |   |   | x | x | x |
  ----------|---|---|---|---|---|---|---|---|

*/

#ifndef MiniStepper_h
#define MiniStepper_h

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
		byte currentPosition; // N out of 4,096 (steps per revolution)
		byte currentStep; // Still need this, as 0 might not be at the first step
		unsigned int rpm;
		unsigned int stepsPerRevolution;
}

#endif