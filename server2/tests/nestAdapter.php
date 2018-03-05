<?php

use PHPUnit\Framework\TestCase;

final class NestTest extends TestCase
{
    private function getAdapter($data)
    {
        $mock = $this->createMock(\Thermostat\Connector\Nest::class);
        $mock->method(getData)
            ->willReturn((object)$data);
        return new \Thermostat\Nest($mock);
    }

    public function testMode(): void
    {
        $this->assertEquals(
            $this->getAdapter(['hvac_mode'=>'eco'])->getMode(),
            \Thermostat\iThermostat::MODE_AUTO
        );
        $this->assertEquals(
            $this->getAdapter(['hvac_mode'=>'heat-cool'])->getMode(),
            \Thermostat\iThermostat::MODE_AUTO
        );
        $this->assertEquals(
            $this->getAdapter(['hvac_mode'=>'heat'])->getMode(),
            \Thermostat\iThermostat::MODE_HEAT
        );
        $this->assertEquals(
            $this->getAdapter(['hvac_mode'=>'cool'])->getMode(),
            \Thermostat\iThermostat::MODE_COOL
        );
    }

    public function deltaTProvider() {
        return [
            [1, ['hvac_mode'=>'heat', 'target_temperature_f' => 70, 'ambient_temperature_f'=>71]],
            [-1, ['hvac_mode'=>'heat', 'target_temperature_f' => 70, 'ambient_temperature_f'=>69]],
            [0, ['hvac_mode'=>'heat', 'target_temperature_f' => 70, 'ambient_temperature_f'=>70]],
            [-1, ['hvac_mode'=>'cool', 'target_temperature_f' => 70, 'ambient_temperature_f'=>71]],
            [1, ['hvac_mode'=>'cool', 'target_temperature_f' => 70, 'ambient_temperature_f'=>69]],
            [6, ['hvac_mode'=>'cool', 'target_temperature_f' => 75, 'ambient_temperature_f'=>69]],
            [-1, ['hvac_mode'=>'heat-cool',
                'target_temperature_low_f' => 70, 'target_temperature_high_f' => 75, 'ambient_temperature_f'=>69]],
            [2, ['hvac_mode'=>'heat-cool',
                'target_temperature_low_f' => 70, 'target_temperature_high_f' => 75, 'ambient_temperature_f'=>72]],
            // Heat is checked first, so it wins ties
            [2, ['hvac_mode'=>'heat-cool',
                'target_temperature_low_f' => 70, 'target_temperature_high_f' => 74, 'ambient_temperature_f'=>72]],
            [2, ['hvac_mode'=>'heat-cool',
                'target_temperature_low_f' => 70, 'target_temperature_high_f' => 75, 'ambient_temperature_f'=>73]],
            [0, ['hvac_mode'=>'heat-cool',
                'target_temperature_low_f' => 70, 'target_temperature_high_f' => 75, 'ambient_temperature_f'=>75]],
            [-10, ['hvac_mode'=>'heat-cool',
                'target_temperature_low_f' => 70, 'target_temperature_high_f' => 75, 'ambient_temperature_f'=>85]],
            [-1, ['hvac_mode'=>'eco',
                'eco_temperature_low_f' => 70, 'eco_temperature_high_f' => 75, 'ambient_temperature_f'=>69]],
            [2, ['hvac_mode'=>'eco',
                'eco_temperature_low_f' => 70, 'eco_temperature_high_f' => 75, 'ambient_temperature_f'=>72]],
            // Heat is checked first, so it wins ties
            [2, ['hvac_mode'=>'eco',
                'eco_temperature_low_f' => 70, 'eco_temperature_high_f' => 74, 'ambient_temperature_f'=>72]],
            [2, ['hvac_mode'=>'eco',
                'eco_temperature_low_f' => 70, 'eco_temperature_high_f' => 75, 'ambient_temperature_f'=>73]],
            [0, ['hvac_mode'=>'eco',
                'eco_temperature_low_f' => 70, 'eco_temperature_high_f' => 75, 'ambient_temperature_f'=>75]],
            [-10, ['hvac_mode'=>'eco',
                'eco_temperature_low_f' => 70, 'eco_temperature_high_f' => 75, 'ambient_temperature_f'=>85]],
        ];
    }

    /**
    * @dataProvider deltaTProvider
    */
    public function testDeltaT($delta, $data): void
    {
        $this->assertEquals(
            $delta,
            $this->getAdapter($data)->deltaT()
        );
    }
}
