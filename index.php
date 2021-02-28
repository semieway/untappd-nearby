<?php

require_once 'vendor/autoload.php';

use App\Database;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$page = $_GET['page'] ?? 1;
$search = $_GET['q'] ?? '';
$db = new Database();

$checkins = (empty($search)) ? $db->getCheckins($page) : $db->beerSearch($search);
foreach ($checkins as &$checkin) {
    $dt = new DateTime($checkin['created'], new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Yekaterinburg'));
    $checkin['created'] = $dt->format('d.m.Y H:i:s (e)');

    $checkin['created_ago'] = $db->time_elapsed_string($dt->format('Y-m-d H:i:s'));
}

$beersCount = $db->getTotalBeersCount()[0]['count'];

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);

echo $twig->render('list.html.twig', [
    'title' => 'Untappd Ekaterinburg',
    'checkins' => $checkins,
    'current' => $page,
    'count' => intval($beersCount),
    'search' => $search
]);
