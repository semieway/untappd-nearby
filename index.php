<?php

require_once 'vendor/autoload.php';

use App\Database;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$page = $_GET['page'] ?? 1;

$db = new Database();
$checkins = $db->getCheckins($page);
foreach ($checkins as &$checkin) {
    $checkin['created_ago'] = $db->time_elapsed_string($checkin['created']);

    $dt = new DateTime($checkin['created'], new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Yekaterinburg'));
    $checkin['created'] = $dt->format('d.m.Y H:m:s (e)');
}

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);

echo $twig->render('list.html.twig', [
    'title' => 'Untappd Ekaterinburg',
    'checkins' => $checkins
]);
