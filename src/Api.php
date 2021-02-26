<?php


namespace App;

use GuzzleHttp\Client;

class Api
{

    private $clientId;
    private $clientSecret;
    private Client $client;

    public function __construct()
    {
        $this->clientId = getenv('CLIENT_ID');
        $this->clientSecret = getenv('CLIENT_SECRET');

        $this->client = new Client([
            'base_uri' => 'https://api.untappd.com/v4/'
        ]);
    }

    public function fetchCheckins(): array
    {
        $response = $this->client->request('GET', 'thepub/local', [
            'query' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'lat' => '56.838729',
                'lng' => '60.603284',
                'radius' => '25',
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        return $data['response']['checkins']['items'];
    }

    public function getBeerInfo(string $beerId): array
    {
        $response = $this->client->request('GET', 'beer/info/'.$beerId, [
           'query' => [
               'client_id' => $this->clientId,
               'client_secret' => $this->clientSecret,
           ]
        ]);

        $data = json_decode($response->getBody(), true);

        return $data['response']['beer'];
    }
}