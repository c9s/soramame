<?php
require 'vendor/autoload.php';


$logger = new CLIFramework\Logger;
$curlAgent = new CurlKit\CurlAgent;
$agent = new Soramame\SoramameAgent($curlAgent, $logger);

$attributes = $agent->fetchStationAttributes();
print_r($attributes);

$counties = $agent->fetchCountyList();
foreach ($counties as $countyId => $countyName) {
    $stations = $agent->fetchCountyStations($countyId, $attributes);
    // print_r($stations);
    $station = $stations[0];
    $history = $agent->fetchStationHistory($station['code']);
    print_r($history);
    break;
}


