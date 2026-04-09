<?php

declare(strict_types=1);

namespace App\Api;

use App\Core\HttpClient;

final class TVMazeClient
{
    private const BASE_URL = 'https://api.tvmaze.com';

    public function __construct(private HttpClient $httpClient)
    {
    }

    public function searchShows(string $query): array
    {
        return $this->httpClient->getJson(self::BASE_URL . '/search/shows?q=' . urlencode($query));
    }

    public function showDetails(string $id): array
    {
        $show = $this->httpClient->getJson(self::BASE_URL . '/shows/' . urlencode($id));
        $show['_embedded'] = [
            'episodes' => $this->httpClient->getJson(self::BASE_URL . '/shows/' . urlencode($id) . '/episodes'),
            'seasons' => $this->httpClient->getJson(self::BASE_URL . '/shows/' . urlencode($id) . '/seasons'),
        ];

        return $show;
    }
}
