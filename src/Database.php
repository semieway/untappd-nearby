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

    public function getWantedIds()
    {
        $query = pg_query($this->connection, 'SELECT id FROM wanted');
        return pg_fetch_all($query, PGSQL_ASSOC);
    }

    public function removeWantedBeer($id)
    {
        pg_delete($this->connection, 'wanted', ['id' => $id]);
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
        $checkins = array_filter($checkins, function ($checkin) use ($beerIds) { return !in_array($checkin['beer']['bid'], $beerIds); });
        krsort($checkins);

        foreach ($checkins as $checkin) {
            $beer = [];
            $brewery = [];
            $location = [];

            $beer['id'] = $checkin['beer']['bid'];
            $beer['name'] = $checkin['beer']['beer_name'];
            $beer['brewery_id'] = $checkin['brewery']['brewery_id'];
            $beer['location_id'] = $checkin['venue']['venue_id'];
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
            pg_insert($this->connection, 'beers', $beer);
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

    public function getWantedBeers() {
        $result = pg_query($this->connection,'
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
    br.name AS brewery_name
FROM
    wanted AS b 
LEFT JOIN 
    breweries AS br ON b.brewery_id = br.id
ORDER BY 
    created DESC
');

        return pg_fetch_all($result, PGSQL_ASSOC);
    }

    public function insertWantedBeer($id, Api $client)
    {
        $query = pg_query($this->connection, 'SELECT id FROM wanted');
        $beerIds = array_map(function($value) { return $value['id']; }, pg_fetch_all($query, PGSQL_ASSOC));
        if (in_array($id, $beerIds)) return true;

        $beerInfo = $client->getBeerInfo($id);
        if (empty($beerInfo)) return false;

        $beer = [];
        $brewery = [];

        $beer['id'] = $beerInfo['bid'];
        $beer['name'] = $beerInfo['beer_name'];
        $beer['brewery_id'] = $beerInfo['brewery']['brewery_id'];
        if (!empty($beerInfo['beer_label_hd'])) {
            $beer['label'] = $beerInfo['beer_label_hd'];
        } else {
            $beer['label'] = $beerInfo['beer_label'];
        }

        $beer['rating'] = $beerInfo['rating_score'];
        $beer['rating_count'] = $beerInfo['rating_count'];
        $beer['style'] = $beerInfo['beer_style'];
        $beer['abv'] = $beerInfo['beer_abv'];
        $beer['ibu'] = $beerInfo['beer_ibu'];

        $brewery['id'] = $beerInfo['brewery']['brewery_id'];
        $brewery['name'] = $beerInfo['brewery']['brewery_name'];

        pg_insert($this->connection, 'breweries', $brewery);
        pg_insert($this->connection, 'wanted', $beer);

        return true;
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