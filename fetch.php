<?php

use App\Api;
use App\Database;

require_once 'vendor/autoload.php';

$client = new Api();
$checkins = $client->fetchCheckins();

$db = new Database();
$db->insertCheckins($checkins, $client);
