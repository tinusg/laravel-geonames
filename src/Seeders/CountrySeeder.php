<?php

namespace Nevadskiy\Geonames\Seeders;

use Nevadskiy\Geonames\Definitions\FeatureCode;
use Nevadskiy\Geonames\Parsers\CountryInfoParser;
use Nevadskiy\Geonames\Parsers\GeonamesDeletesParser;
use Nevadskiy\Geonames\Parsers\GeonamesParser;
use Nevadskiy\Geonames\Services\DownloadService;

class CountrySeeder extends ModelSeeder
{
    /**
     * The country model class.
     *
     * @var string
     */
    protected static $model = 'App\\Models\\Geo\\Country';

    /**
     * The allowed feature codes.
     *
     * @var array
     */
    protected $featureCodes = [];

    /**
     * The country info list.
     *
     * @var array
     */
    protected $countryInfo = [];

    /**
     * The continent list.
     *
     * @var array
     */
    protected $continents = [];

    /**
     * Make a new seeder instance.
     */
    public function __construct()
    {
        $this->featureCodes = [
            FeatureCode::PCLI,
            FeatureCode::PCLD,
            FeatureCode::TERR,
            FeatureCode::PCLIX,
            FeatureCode::PCLS,
            FeatureCode::PCLF,
            FeatureCode::PCL,
        ];
    }

    /**
     * Use the given country model class.
     */
    public static function useModel(string $model): void
    {
        static::$model = $model;
    }

    /**
     * Get the country model class.
     */
    public static function model(): string
    {
        return static::$model;
    }

    /**
     * {@inheritdoc}
     * @TODO refactor with DI downloader and parser.
     */
    protected function getRecords(): iterable
    {
        $path = resolve(DownloadService::class)->downloadAllCountries();

        foreach (resolve(GeonamesParser::class)->each($path) as $record) {
            yield $record;
        }
    }

    /**
     * {@inheritdoc}
     * @TODO refactor with DI downloader and parser.
     */
    protected function getDailyModificationRecords(): iterable
    {
        $path = resolve(DownloadService::class)->downloadDailyModifications();

        foreach (resolve(GeonamesParser::class)->each($path) as $record) {
            yield $record;
        }
    }

    /**
     * {@inheritdoc}
     * @TODO refactor with DI downloader and parser.
     */
    protected function getDailyDeleteRecords(): iterable
    {
        $path = resolve(DownloadService::class)->downloadDailyDeletes();

        foreach (resolve(GeonamesDeletesParser::class)->each($path) as $record) {
            yield $record;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function loadResourcesBeforeMapping(): void
    {
        $this->loadCountryInfo();
        $this->loadContinents();
    }

    /**
     * {@inheritdoc}
     */
    protected function unloadResourcesAfterMapping(): void
    {
        $this->countryInfo = [];
        $this->continents = [];
    }

    /**
     * Load the country info resources.
     */
    protected function loadCountryInfo(): void
    {
        // TODO: refactor downloading by passing Downloader instance from constructor.
        $path = resolve(DownloadService::class)->downloadCountryInfo();

        $this->countryInfo = collect(resolve(CountryInfoParser::class)->all($path))
            ->keyBy('geonameid')
            ->all();
    }

    /**
     * Load the continent resources.
     */
    protected function loadContinents(): void
    {
        $this->continents = ContinentSeeder::newModel()
            ->newQuery()
            ->get()
            ->pluck('id', 'code')
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    protected function filter(array $record): bool
    {
        if (! isset($this->countryInfo[$record['geonameid']])) {
            return false;
        }

        return in_array($record['feature code'], $this->featureCodes, true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapAttributes(array $record): array
    {
        return array_merge($this->mapCountryInfoAttributes($record), [
            'name_official' => $record['asciiname'] ?: $record['name'],
            'latitude' => $record['latitude'],
            'longitude' => $record['longitude'],
            'timezone_id' => $record['timezone'],
            'population' => $record['population'],
            'elevation' => $record['elevation'],
            'dem' => $record['dem'],
            'feature_code' => $record['feature code'],
            'geoname_id' => $record['geonameid'],
            'synced_at' => $record['modification date'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Map attributes of the country info record.
     */
    protected function mapCountryInfoAttributes(array $record): array
    {
        $countryInfo = $this->countryInfo[$record['geonameid']];

        return [
            'code' => $countryInfo['ISO'],
            'iso' => $countryInfo['ISO3'],
            'iso_numeric' => $countryInfo['ISO-Numeric'],
            'name' => $countryInfo['Country'],
            'continent_id' => $this->continents[$countryInfo['Continent']],
            'capital' => $countryInfo['Capital'],
            'currency_code' => $countryInfo['CurrencyCode'],
            'currency_name' => $countryInfo['CurrencyName'],
            'tld' => $countryInfo['tld'],
            'phone_code' => $countryInfo['Phone'],
            'postal_code_format' => $countryInfo['Postal Code Format'],
            'postal_code_regex' => $countryInfo['Postal Code Regex'],
            'languages' => $countryInfo['Languages'],
            'neighbours' => $countryInfo['neighbours'],
            'area' => $countryInfo['Area(in sq km)'],
            'fips' => $countryInfo['fips'],
        ];
    }
}
