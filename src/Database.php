<?php


namespace App;


class Database
{

    private $connection;

    public function __construct()
    {
        $this->connection = getenv('DATABASE_URL');
    }

    public function getCheckins($page)
    {
        $offset = ($page - 1) * 5;
        $result = pg_prepare($this->connection, 'get_checkins','SELECT * FROM beers ORDER BY created DESC LIMIT 5 OFFSET $1;');
        $result = pg_execute($this->connection, 'get_checkins', [$offset]);

        return pg_fetch_all($result, PGSQL_ASSOC);
    }

    public function insertCheckins(array $checkins, Api $client): void
    {
        $query = pg_query($this->connection, 'SELECT id FROM beers');
        $beerIds = array_map(function($value) { return $value['id']; }, pg_fetch_all($query, PGSQL_ASSOC));
        $checkins = array_filter($checkins, function ($checkin) use ($beerIds) { return !in_array($checkin['beer']['bid'], $beerIds); });

        foreach ($checkins as $checkin) {
            $beer = [];
            $brewery = [];
            $location = [];

            $beer['id'] = $checkin['beer']['bid'];
            $beer['name'] = $checkin['beer']['beer_name'];
            $beer['brewery_id'] = $checkin['brewery']['brewery_id'];
            $beer['location_id'] = $checkin['venue']['venue_id'];
            $beer['label'] = $checkin['beer']['beer_label_hd'];

            $beerInfo = $client->getBeerInfo($beer['id']);
            $beer['rating'] = $beerInfo['rating_score'];
            $beer['checkins_count'] = $beerInfo['stats']['total_count'];
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
}