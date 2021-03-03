<?php

namespace App;

use DateTime;

class Database
{

    private $connection;

    public function __construct()
    {
        $this->connection = pg_connect(getenv('DATABASE_URL'));
    }

    public function getWantedLocations()
    {
        $query = pg_query($this->connection, 'SELECT id, name, address FROM locations WHERE wanted=true');
        return pg_fetch_all($query, PGSQL_ASSOC);
    }

    public function removeWantedBeer($id, $userId)
    {
        pg_delete($this->connection, 'wanted', ['beer_id' => $id, 'user_id' => $userId]);
    }

    public function getLastCheckin()
    {
        $query = pg_query($this->connection, 'SELECT last_checkin FROM beers ORDER BY created DESC LIMIT 1');
        return pg_fetch_all($query, PGSQL_ASSOC)[0]['last_checkin'];
    }

    public function updateWantedBeerLocations($id, $locations, $userId)
    {
        pg_prepare($this->connection, 'update_wanted_beer_locations', 'UPDATE wanted SET locations = $1 WHERE beer_id = $2 AND user_id = $3');
        pg_execute($this->connection, 'update_wanted_beer_locations', [$locations, $id, $userId]);
    }

    public function getUsers()
    {
        $query = pg_query($this->connection, 'SELECT * FROM users');
        return pg_fetch_all($query, PGSQL_ASSOC);
    }

    public function getCheckins($page = 1)
    {
        $offset = ($page - 1) * 25;

        $result = pg_prepare($this->connection, 'get_checkins','
SELECT 
    b.id, 
    b.name,
    b.rating, 
    b.rating_count, 
    b.label, 
    b.style, 
    b.abv, 
    b.ibu,
    b.created, 
    br.id AS brewery_id,
    br.name AS brewery_name,
    l.name AS location_name,
    l.address AS location_address
FROM
    beers AS b 
LEFT JOIN
    locations AS l ON b.location_id = l.id
LEFT JOIN 
    breweries AS br ON b.brewery_id = br.id
ORDER BY 
    created DESC 
LIMIT 25 
OFFSET $1;
');
        $result = pg_execute($this->connection, 'get_checkins', [$offset]);

        return pg_fetch_all($result, PGSQL_ASSOC);
    }

    public function getTotalBeersCount()
    {
        $query = pg_query($this->connection, 'SELECT COUNT(id) FROM beers');
        return pg_fetch_all($query, PGSQL_ASSOC);
    }

    public function insertCheckins(array $checkins, Api $client): void
    {
        $query = pg_query($this->connection, 'SELECT id FROM beers');
        $beerIds = array_map(function($value) { return $value['id']; }, pg_fetch_all($query, PGSQL_ASSOC));
        krsort($checkins);

        foreach ($checkins as $checkin) {
            $beer = [];
            $brewery = [];
            $location = [];

            $beer['id'] = $checkin['beer']['bid'];
            $beer['name'] = $checkin['beer']['beer_name'];
            $beer['brewery_id'] = $checkin['brewery']['brewery_id'];
            $beer['location_id'] = $checkin['venue']['venue_id'];
            $beer['last_checkin'] = $checkin['checkin_id'];
            if (!empty($checkin['beer']['beer_label_hd'])) {
                $beer['label'] = $checkin['beer']['beer_label_hd'];
            } else {
                $beer['label'] = $checkin['beer']['beer_label'];
            }

            $beerInfo = $client->getBeerInfo($beer['id']);
            if (empty($beerInfo)) continue;

            $beer['rating'] = $beerInfo['rating_score'];
            $beer['rating_count'] = $beerInfo['rating_count'];
            $beer['style'] = $beerInfo['beer_style'];
            $beer['abv'] = $beerInfo['beer_abv'];
            $beer['ibu'] = $beerInfo['beer_ibu'];

            $brewery['id'] = $beerInfo['brewery']['brewery_id'];
            $brewery['name'] = $beerInfo['brewery']['brewery_name'];

            $location['id'] = $checkin['venue']['venue_id'];
            $location['name'] = $checkin['venue']['venue_name'];
            $location['address'] = $checkin['venue']['location']['venue_address'];

            pg_insert($this->connection, 'breweries', $brewery);
            pg_insert($this->connection, 'locations', $location);
            if (in_array($checkin['beer']['bid'], $beerIds)) {
                $beer['created'] = DateTime::createFromFormat('U.u', microtime(true))->format('Y-m-d H:i:s.u');
                pg_update($this->connection, 'beers', $beer, ['id' => $beer['id']]);
            } else {
                pg_insert($this->connection, 'beers', $beer);
            }
        }
    }

    public function beerSearch($search = '')
    {
        $result = pg_prepare($this->connection, 'beer_search','
SELECT 
    b.id, 
    b.name,
    b.rating, 
    b.rating_count, 
    b.label, 
    b.style, 
    b.abv, 
    b.ibu,
    b.created, 
    br.id AS brewery_id,
    br.name AS brewery_name,
    l.name AS location_name,
    l.address AS location_address
FROM
    beers AS b 
LEFT JOIN
    locations AS l ON b.location_id = l.id
LEFT JOIN 
    breweries AS br ON b.brewery_id = br.id
WHERE
    LOWER(b.name) LIKE LOWER($1)
OR  LOWER(br.name) LIKE LOWER($1)
ORDER BY 
    created DESC
');

        $result = pg_execute($this->connection, 'beer_search', ['%'.$search.'%']);

        return pg_fetch_all($result, PGSQL_ASSOC);
    }

    public function getWantedBeers($id) {
        pg_prepare($this->connection, 'get_wanted_beers', '
SELECT 
    b.beer_id, 
    b.name,
    b.locations,
    br.id AS brewery_id,
    br.name AS brewery_name
FROM
    wanted AS b 
LEFT JOIN 
    breweries AS br ON b.brewery_id = br.id
WHERE
    b.user_id = $1
');
        $result = pg_execute($this->connection, 'get_wanted_beers', [$id]);
        return pg_fetch_all($result, PGSQL_ASSOC);
    }

    public function insertWantedBeer($beerInfo, $userId)
    {
        $beer = [];
        $brewery = [];

        $beer['beer_id'] = $beerInfo['beer']['bid'];
        $beer['name'] = $beerInfo['beer']['beer_name'];
        $beer['user_id'] = $userId;
        $beer['brewery_id'] = $beerInfo['brewery']['brewery_id'];

        $brewery['id'] = $beerInfo['brewery']['brewery_id'];
        $brewery['name'] = $beerInfo['brewery']['brewery_name'];

        pg_insert($this->connection, 'breweries', $brewery);
        pg_insert($this->connection, 'wanted', $beer);
    }

    public function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime('now', new \DateTimeZone('Asia/Yekaterinburg'));
        $ago = new DateTime($datetime, new \DateTimeZone('Asia/Yekaterinburg'));
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }

}