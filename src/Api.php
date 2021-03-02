<?php


namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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

    public function fetchCheckins($minCheckinId = ''): array
    {
        $response = $this->client->request('GET', 'thepub/local', [
            'query' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'lat' => '56.838729',
                'lng' => '60.603284',
                'radius' => '25',
                'min_id' => $minCheckinId
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        return $data['response']['checkins']['items'];
    }

    public function getBeerInfo(string $beerId)
    {
        try {
            $response = $this->client->request('GET', 'beer/info/' . $beerId, [
                'query' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]
            ]);
        } catch (GuzzleException $e) {
        }

        if (!empty($response)) {
            $data = json_decode($response->getBody(), true);

            return $data['response']['beer'];
        } else {

            return null;
        }
    }
}