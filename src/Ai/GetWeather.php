<?php

declare(strict_types=1);

namespace App\Ai;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsTool(name: 'get_weather', description: 'Current temperature and wind for a city.')]
final class GetWeather
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @param string $city name of the city, for example "Vienna"
     *
     * @return array{city: string, temperature_c: float, wind_kmh: float}
     */
    public function __invoke(string $city): array
    {
        $place = $this->httpClient->request('GET', 'https://geocoding-api.open-meteo.com/v1/search', [
            'query' => ['name' => $city, 'count' => 1],
        ])->toArray()['results'][0] ?? throw new \RuntimeException(\sprintf('Unknown city "%s".', $city));

        $current = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
            'query' => [
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
                'current' => 'temperature_2m,wind_speed_10m',
            ],
        ])->toArray()['current'];

        return [
            'city' => $place['name'],
            'temperature_c' => $current['temperature_2m'],
            'wind_kmh' => $current['wind_speed_10m'],
        ];
    }
}
