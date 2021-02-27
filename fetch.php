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

$ids = array_map(function($checkin) { return $checkin['beer']['bid']; }, $checkins);
$result = array_filter($checkins, function($checkin) use ($ids) { in_array($checkin['beer']['bid'], $ids); });

if (!empty($result)) {
    $transport = (new \Swift_SmtpTransport('smtp.gmail.com', 465))
        ->setUsername(getenv('EMAIL_USERNAME'))
        ->setPassword('EMAIL_PASSWORD')
        ->setEncryption('SSL');
    $mailer = new \Swift_Mailer($transport);

    $loader = new FilesystemLoader(__DIR__ . '/templates');
    $twig = new Environment($loader);

    foreach ($result as $checkin) {
        $message = (new \Swift_Message('Beer «'.$checkin['beer']['beer_name'].'» checkin nearby!'))
            ->setFrom('semieway@gmail.com', 'Untappd')
            ->setTo(['semieway@gmail.com'])
            ->setBody(
                $twig->render(
                    'mail.html.twig',
                    ['checkin' => $checkin]
                ),
                'text/html'
            );

        $mailer->send($message);
    }
}
