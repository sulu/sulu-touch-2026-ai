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
        $places = $this->httpClient->request('GET', 'https://geocoding-api.open-meteo.com/v1/search', [
            'query' => ['name' => $city, 'count' => 1],
        ])->toArray()['results'] ?? null;
        $place = \is_array($places) ? ($places[0] ?? null) : null;
        if (!\is_array($place)) {
            throw new \RuntimeException(\sprintf('Unknown city "%s".', $city));
        }

        $current = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
            'query' => [
                'latitude' => $place['latitude'] ?? null,
                'longitude' => $place['longitude'] ?? null,
                'current' => 'temperature_2m,wind_speed_10m',
            ],
        ])->toArray()['current'] ?? null;

        $name = $place['name'] ?? null;
        $temperature = \is_array($current) ? ($current['temperature_2m'] ?? null) : null;
        $wind = \is_array($current) ? ($current['wind_speed_10m'] ?? null) : null;
        if (!\is_string($name) || !\is_numeric($temperature) || !\is_numeric($wind)) {
            throw new \RuntimeException(\sprintf('The weather service sent no current weather for "%s".', $city));
        }

        return [
            'city' => $name,
            'temperature_c' => (float) $temperature,
            'wind_kmh' => (float) $wind,
        ];
    }
}
