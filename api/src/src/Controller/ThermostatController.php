<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Service\Thermostat;

class ThermostatController extends Controller
{
    public function list(Thermostat $tstat)
    {
        //$db = $this->container->get('snc_redis.default');
        return $this->json($tstat->getList());
    }

    public function status($id)
    {
        return $this->json(['thermostat_id' => $id]);
    }
}
