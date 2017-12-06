<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ThermostatController extends Controller
{
    public function list()
    {
        return $this->json(['one', 'two', 'three']);
    }

    public function status($id)
    {
        return $this->json(['thermostat_id' => $id]);
    }
}
