<?php

require_once __DIR__ . "/../lib/airflow.php";

// Basic 2-zone tests
$flow = new Airflow([1=>0, 2=>100]);
$flow->addZone(1, 2, false);
$flow->addZone(2, 2, true);
assert([] == array_diff_assoc($flow->getEnforced(2), [1=>0, 2=>100]), "No changes necessary");
assert([] == array_diff_assoc($flow->getEnforced(3), [1=>50, 2=>100]), "Partially open 1 zone");
assert([] == array_diff_assoc($flow->getEnforced(4), [1=>100, 2=>100]), "Fully open 1 zone");
assert([] == array_diff_assoc($flow->getEnforced(5), [1=>100, 2=>100]), "Not enough airflow");
unset($flow);

// 3-zone tests
$flow = new Airflow([1=>0, 2=>100, 3=>0]);
$flow->addZone(1, 2, false);
$flow->addZone(2, 2, true);
$flow->addZone(3, 2, false);
assert([] == array_diff_assoc($flow->getEnforced(2), [1=>0, 2=>100, 3=>0]), "No changes necessary");
assert([] == array_diff_assoc($flow->getEnforced(3), [1=>25, 2=>100, 3=>25]), "2 zones 25% open");
assert([] == array_diff_assoc($flow->getEnforced(6), [1=>100, 2=>100, 3=>100]), "Fully open all zones");
assert([] == array_diff_assoc($flow->getEnforced(7), [1=>100, 2=>100, 3=>100]), "Not enough airflow");
unset($flow);

// 3-zone non-master open
$flow = new Airflow([1=>0, 2=>100, 3=>0]);
$flow->addZone(1, 2, true);
$flow->addZone(2, 2, false);
$flow->addZone(3, 2, false);
assert([] == array_diff_assoc($flow->getEnforced(3), [1=>0, 2=>100, 3=>50]), "partially open non-master zone");
assert([] == array_diff_assoc($flow->getEnforced(5), [1=>50, 2=>100, 3=>100]), "Fully open non-masetr zone, and partially master");
assert([] == array_diff_assoc($flow->getEnforced(6), [1=>100, 2=>100, 3=>100]), "Fully open all zones");
unset($flow);

// 3 zone partial open start
$flow = new Airflow([1=>0, 2=>50, 3=>0]);
$flow->addZone(1, 2, true);
$flow->addZone(2, 4, false);
$flow->addZone(3, 2, false);
assert([] == array_diff_assoc($flow->getEnforced(4), [1=>0, 2=>75, 3=>50]), "increase opening when starting with non-100 percent");
unset($flow);
