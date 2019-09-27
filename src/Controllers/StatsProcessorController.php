<?php
/**
 * Finna statistics utility scripts.
 * Copyright (c) 2015-2018 University Of Helsinki (The National Library Of Finland)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @author    Ere Maijala <ere.maijala@helsinki.fi>
 * @copyright 2015-2018 University Of Helsinki (The National Library Of Finland)
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 */

namespace Finna\Stats\StatsProcessor;

require_once(__DIR__ . '/../Abstracts/BaseAbstract.php');

use Finna\Stats\BaseAbstract\BaseAbstract as Base;

/**
 * Processes result counts from the Solr index.
 */
class StatsProcessorController extends Base
{
    /** @var string The query URL to the Solr index */
    private $url;

    /** @var string[] List of filters that can be applied to searches */
    private $filters;

    /** @var string[] List of custom queries that will be performed */
    private $queries;

    public function __construct(\PDO $pdo, $settings)
    {
        parent::__construct($pdo, $settings);
        $this->url = (string)$settings['url'];
        $this->filters = $settings['filters'];
        $this->queries = $settings['queries'];
    }

    public function run()
    {
        $results = $processor->processFilterQueries($settings['filterSets']);
        return $results;
    }

    /**
     * Returns specific named filter.
     * @param string $name Name of the filter
     * @return string The filter string
     * @throws \InvalidArgumentException If the filter does not exist
     */
    public function getFilter($name)
    {
        if (!isset($this->filters[$name])) {
            throw new \InvalidArgumentException("Invalid filter '$name'");
        }

        return $this->filters[$name];
    }

    /**
     * Returns the results counts for different queries based on different filter combinations.
     *
     * The provided array must consist of arrays of filter names. These filter
     * combinations are applied to each query. Each result row contains the
     * result counts for each combination of filters in the order they were
     * passed to this method.
     *
     * @param array $filterSets List of different filter combinations
     * @return array Results for the filter combination counts
     */
    public function processFilterQueries(array $filterSets)
    {
        $timer = microtime(true);
        $this->log('Statistics processing started at ' . date('r'));

        $results = [];
        $now = date('c');

        foreach ($this->getQueries() as $query) {
            $counts = array_map(function (\SimpleXMLElement $xml) {
                return (int) $xml->result['numFound'];
            }, $this->fetchMultiple($this->buildQuerySet($query, $filterSets)));

            $results[] = array_merge([$now, 'q=' . $query], $counts);
        }

        $this->log(sprintf(
            'Statistics processing finished in %d ms at %s',
            (microtime(true) - $timer) * 1000,
            date('r')
        ));

        return $results;
    }

    /**
     * Returns all the different queries.
     * @return string[] List of all the queries to perform
     */
    private function getQueries()
    {
        return array_merge($this->queries, $this->getSectorQueries(), $this->getFormatQueries());
    }

    /**
     * Returns the list of sector queries to perform fetched from the index.
     * @return string[] List of sector queries.
     */
    private function getSectorQueries()
    {
        $xml = $this->fetchXml(['q' => '*:*', 'rows' => '0', 'facet' => 'true', 'facet.field' => 'sector_str_mv']);
        $queries = [];

        foreach ($xml->xpath('//lst[@name="sector_str_mv"]/int') as $sectorItem) {
            $queries[] = sprintf('sector_str_mv:"%s"', (string) $sectorItem['name']);
        }

        return $queries;
    }

    /**
     * Returns the list of format queries to perform fetched from the index.
     * @return string[] List of format queries
     */
    private function getFormatQueries()
    {
        $xml = $this->fetchXml(
            ['q' => '*:*', 'rows' => '0', 'facet' => 'true', 'facet.field' => 'format', 'facet.prefix' => '0']
        );
        $queries = [];

        foreach ($xml->xpath('//lst[@name="format"]/int') as $formatItem) {
            $queries[] = sprintf('format:"%s"', (string) $formatItem['name']);
        }

        return $queries;
    }

    /**
     * Fetches the xml object from the Solr index.
     * @param string[] $params List of query parameters to the Solr query url
     * @return \SimpleXMLElement XML object retrieved from the Solr index
     */
    private function fetchXml(array $params)
    {
        $timer = microtime(true);
        $curl = $this->initCurl($params);

        $result = curl_exec($curl);
        $this->validateCurlResponse($curl);

        curl_close($curl);
        $this->log(sprintf('Executed 1 request in %d ms', (microtime(true) - $timer) * 1000));

        return $this->getXml($result);
    }

    /**
     * Initializes a curl handle with the known Solr url and given parameters.
     * @param string[] $params List of query parameters
     * @return resource Initialized curl handle
     */
    private function initCurl(array $params)
    {
        $curl = curl_init();

        $this->log("Initializing request: " . urldecode(http_build_query($params, '', '&')));

        $params['wt'] = 'xml';
        $url = $this->url;
        $url .= strpos($url, '?') !== false ? '&' : '?';
        $url .= http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        return $curl;
    }

    /**
     * Makes sure that no errors occurred in the curl process.
     * @param resource $curl The curl process to validate
     * @throws \RuntimeException If error occurred in the curl process
     */
    private function validateCurlResponse($curl)
    {
        if (curl_errno($curl)) {
            throw new \RuntimeException('CURL error fetching from Solr: ' . curl_error($curl));
        } elseif (200 !== curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
            throw new \RuntimeException(
                'Unexpected response code from Solr: ' . curl_getinfo($curl, CURLINFO_HTTP_CODE)
            );
        }
    }

    /**
     * Returns the xml object from the Solr response string.
     * @param String $string String to turn into xml object
     * @return \SimpleXMLElement The xml object generated from the string
     * @throws \UnexpectedValueException If the string is not a valid Solr response
     */
    private function getXml($string)
    {
        $xml = new \SimpleXMLElement($string);

        if ($xml->result['numFound'] === null) {
            throw new \UnexpectedValueException('Invalid response from Solr');
        }

        return $xml;
    }

    /**
     * Uses multi threaded processing to make multiple parallel requests.
     * @param array $queries Array of query parameter arrays.
     * @return \SimpleXMLElement[] The xml objects from the queries
     */
    private function fetchMultiple(array $queries)
    {
        if (count($queries) < 2) {
            return [$this->fetchXml(reset($queries))];
        }

        $timer = microtime(true);
        $curls = array_map([$this, 'initCurl'], $queries);

        $multi = curl_multi_init();
        array_map(function ($curl) use ($multi) {
            curl_multi_add_handle($multi, $curl);
        }, $curls);

        $this->executeMulti($multi);
        array_map([$this, 'validateCurlResponse'], $curls);
        $results = array_map('curl_multi_getcontent', $curls);

        array_map(function ($curl) use ($multi) {
            curl_multi_remove_handle($multi, $curl);
        }, $curls);
        curl_multi_close($multi);
        array_map('curl_close', $curls);
        $this->log(sprintf('Executed %d requests in %d ms', count($queries), (microtime(true) - $timer) * 1000));

        return array_map([$this, 'getXml'], $results);
    }

    /**
     * Processes the curl multi handle until the all handles are finished.
     * @param resource $multi Curl multi handle to process
     */
    private function executeMulti($multi)
    {
        $active = false;

        do {
            $code = curl_multi_exec($multi, $active);
        } while ($code === CURLM_CALL_MULTI_PERFORM);

        while ($active && $code === CURLM_OK) {
            if (curl_multi_select($multi) === -1) {
                usleep(100);
            }

            do {
                $code = curl_multi_exec($multi, $active);
            } while ($code == CURLM_CALL_MULTI_PERFORM);
        }
    }

    /**
     * Builds queries for each filter combination.
     * @param string $query The query parameter for the request
     * @param array $filterSets Filter combinations for different queries
     * @return array Parameter sets for all the queries.
     */
    private function buildQuerySet($query, array $filterSets)
    {
        $queries = [];

        foreach ($filterSets as $filterSet) {
            $params = ['q' => $query];

            if (count($filterSet) > 0) {
                $params['fq'] = implode(' AND ', array_map([$this, 'getFilter'], $filterSet));
            }

            $queries[] = $params;
        }

        return $queries;
    }

    /**
     * Outputs a message to the log.
     * @param string $message Message to output
     */
    protected function log($message)
    {
        echo $message . PHP_EOL;
    }
}
