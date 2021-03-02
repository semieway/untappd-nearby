<?php

use App\Api;
use App\Database;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once 'vendor/autoload.php';

$client = new Api();
$checkins = $client->fetchCheckins();

$db = new Database();
$db->insertCheckins($checkins, $client);

$ids = array_map(function ($id) { return $id['id']; }, $db->getWantedIds());
$result = array_filter($checkins, function($checkin) use ($ids) { return in_array($checkin['beer']['bid'], $ids); });

if (!empty($result)) {
    $transport = (new \Swift_SmtpTransport('smtp.gmail.com', 465))
        ->setUsername(getenv('EMAIL_USERNAME'))
        ->setPassword('EMAIL_PASSWORD')
        ->setEncryption('SSL');
    $mailer = new \Swift_Mailer($transport);

    $loader = new FilesystemLoader(__DIR__ . '/templates');
    $twig = new Environment($loader);

    foreach ($result as $checkin) {
        $message = (new \Swift_Message('«'.$checkin['beer']['beer_name'].'» just checkined nearby!'))
            ->setFrom('semieway@gmail.com', 'Untappd')
            ->setTo(['semieway@gmail.com', 'fllwurdrmss@gmail.com'])
            ->setBody(
                $twig->render(
                    'mail.html.twig',
                    ['checkin' => $checkin]
                ),
                'text/html'
            );

        $mailer->send($message);
    }

    $db->removeWantedBeer($checkin['beer']['bid']);
}
