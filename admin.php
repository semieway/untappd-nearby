<?php

require_once 'vendor/autoload.php';

use App\Api;
use App\Database;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function require_auth() {
    $AUTH_USER = 'admin';
    $AUTH_PASS = 'ekb';

    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    $has_supplied_credentials = !(empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW']));

    $is_not_authenticated = (
        !$has_supplied_credentials ||
        $_SERVER['PHP_AUTH_USER'] != $AUTH_USER ||
        $_SERVER['PHP_AUTH_PW']   != $AUTH_PASS
    );

    if ($is_not_authenticated) {
        header('HTTP/1.1 401 Authorization Required');
        header('WWW-Authenticate: Basic realm="Access denied"');
        exit;
    }
}

require_auth();

$db = new Database();
$error = '';

if (isset($_POST['id'])) {
    if (is_numeric($_POST['id']))  {
        $id = $_POST['id'];
    } else {
        $pieces = explode('/', $_POST['id']);
        $id = $pieces[count($pieces) - 1];
    }

    $client = new Api();
    $result = $db->insertWantedBeer($id, $client);
    $error = ($result) ? '' : 'Failed to add beer, please check that format is correct';
}

if (isset($_POST['remove_id'])) {
    $db->removeWantedBeer($_POST['remove_id']);
}

$beers = $db->getWantedBeers();

foreach ($beers as &$beer) {
    $dt = new DateTime($beer['created'], new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Yekaterinburg'));
    $beer['created'] = $dt->format('d.m.Y H:i:s (e)');

    $beer['created_ago'] = $db->time_elapsed_string($dt->format('Y-m-d H:i:s'));
}

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);

echo $twig->render('list.html.twig', [
    'checkins' => $beers,
    'current' => 0,
    'count' => 0,
    'search' => '',
    'admin_form' => true,
    'error' => $error
]);

