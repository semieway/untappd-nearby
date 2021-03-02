<?php

use App\Api;
use App\Database;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once 'vendor/autoload.php';

$client = new Api();
$db = new Database();
$lastCheckin = $db->getLastCheckin();

$checkins = $client->fetchCheckins($lastCheckin);
$db->insertCheckins($checkins, $client);

$wantedBeers = array_column($db->getWantedBeers(), null, 'id');
$wantedLocations = array_column($db->getWantedLocations(), null, 'id');
$ids = array_column($wantedBeers, 'id');
$result = array_filter($checkins, function($checkin) use ($ids, &$wantedBeers) {
    if (in_array($checkin['beer']['bid'], $ids)) {
        unset($wantedBeers[$checkin['beer']['bid']]);
        return true;
    } else {
        return false;
    }
});

foreach ($wantedBeers as $wantedBeer) {
    $locIds = (empty($wantedBeer['locations'])) ? [] : explode(',', $wantedBeer['locations']);
    $page = file_get_contents('https://untappd.com/beer/'.$wantedBeer['id']);
    @$doc = new DOMDocument();
    @$doc->loadHTML($page);

    $xpath = new DOMXPath($doc);
    $locations = $xpath->query("//p[@class='purchased']/a/@href");
    $count = $locations->count();
    for ($i = 0; $i < $count; $i++) {
        $id = explode('/', $locations->item($i)->nodeValue)[3];
        if (isset($wantedLocations[$id]) && !in_array($id, $locIds)) {
            $locIds[] = $id;
            $checkin = [];
            $checkin['beer']['bid'] = $wantedBeer['id'];
            $checkin['beer']['beer_name'] = $wantedBeer['name'];
            $checkin['brewery']['brewery_name'] = $wantedBeer['brewery_name'];
            $checkin['venue']['venue_name'] = $wantedLocations[$id]['name'];
            $checkin['venue']['location']['venue_address'] = $wantedLocations[$id]['address'];
            $result[] = $checkin;

            $db->updateWantedBeerLocations($wantedBeer['id'], implode(',', $locIds));
            break;
        }
    }
}

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
}
