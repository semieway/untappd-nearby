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
$users = $db->getUsers();

foreach ($users as $user) {
    $storedBeers = array_column($db->getWantedBeers($user['id']), null, 'beer_id');
    $storedBeerIds = array_column($storedBeers, 'beer_id');
    $wantedLocations = array_column($db->getWantedLocations(), null, 'id');

    $result = [];
    foreach ($checkins as $checkin) {
        if (in_array($checkin['beer']['bid'], $storedBeerIds)) {
            $storedBeers[$checkin['beer']['bid']]['checkin_id'] = $checkin['checkin_id'];
            $storedBeers[$checkin['beer']['bid']]['location_id'] = $checkin['venue']['venue_id'];
            $storedBeers[$checkin['beer']['bid']]['location_name'] = $checkin['venue']['venue_name'];
            $storedBeers[$checkin['beer']['bid']]['location_address'] = $checkin['venue']['location']['venue_address'];
        }
    }

    foreach ($storedBeers as $storedBeer) {
        $checkin = [];
        $found = FALSE;
        $locIds = (empty($storedBeer['locations'])) ? [] : explode(',', $storedBeer['locations']);

        if (isset($storedBeer['checkin_id']) && !in_array($storedBeer['location_id'], $locIds)) {
            $found = TRUE;
            $locIds[] = $storedBeer['location_id'];
            $checkin['venue']['venue_name'] = $storedBeer['location_name'];
            $checkin['venue']['location']['venue_address'] = $storedBeer['location_address'];
        } else {
            $page = file_get_contents('https://untappd.com/beer/' . $storedBeer['beer_id']);
            @$doc = new DOMDocument();
            @$doc->loadHTML($page);

            $xpath = new DOMXPath($doc);
            $locations = $xpath->query("//p[@class='purchased']/a/@href");
            $count = $locations->count();
            for ($i = 0; $i < $count; $i++) {
                $id = explode('/', $locations->item($i)->nodeValue)[3];
                if (isset($wantedLocations[$id]) && !in_array($id, $locIds)) {
                    $found = TRUE;
                    $locIds[] = $id;
                    $checkin['venue']['venue_name'] = $wantedLocations[$id]['name'];
                    $checkin['venue']['location']['venue_address'] = $wantedLocations[$id]['address'];
                    break;
                }
            }
        }

        if ($found) {
            $checkin['checkin_id'] = $storedBeer['checkin_id'] ?? '';
            $checkin['beer']['bid'] = $storedBeer['beer_id'];
            $checkin['beer']['beer_name'] = $storedBeer['name'];
            $checkin['brewery']['brewery_name'] = $storedBeer['brewery_name'];
            $result[] = $checkin;

            $db->updateWantedBeerLocations($storedBeer['beer_id'], implode(',', $locIds), $user['id']);
        }
    }

    if (!empty($result)) {
        $transport = (new \Swift_SmtpTransport('smtp.gmail.com', 465))
            ->setUsername('semieway@gmail.com')
            ->setPassword('akhucdrfwsgatdym')
            ->setEncryption('SSL');
        $mailer = new \Swift_Mailer($transport);

        $loader = new FilesystemLoader(__DIR__ . '/templates');
        $twig = new Environment($loader);

        foreach ($result as $checkin) {
            $message = (new \Swift_Message('«'.$checkin['beer']['beer_name'].'» just checkined nearby!'))
                ->setFrom('semieway@gmail.com', 'Untappd')
                ->setTo($user['email'])
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

    $wishlistedBeers = $client->getWishlist($user['name']);
    $newBeers = array_filter($wishlistedBeers, function($beer) use ($storedBeerIds) { return !in_array($beer['beer']['bid'], $storedBeerIds); });
    foreach ($newBeers as $newBeer) {
        $db->insertWantedBeer($newBeer, $user['id']);
    }

    $wishlistedIds = array_map(function($beer) { return $beer['beer']['bid']; }, $wishlistedBeers);
    $removedBeers = array_diff($storedBeerIds, $wishlistedIds);
    foreach ($removedBeers as $removedBeer) {
        $db->removeWantedBeer($removedBeer, $user['id']);
    }
}
