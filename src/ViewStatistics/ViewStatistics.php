<?php
/**
 * Finna statistics utility scripts.
 * Copyright (c) 2018 University Of Helsinki (The National Library Of Finland)
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
 * @author    Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @copyright 2018 University Of Helsinki (The National Library Of Finland)
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 */
namespace Finna\Stats\ViewStatistics;

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

/**
 * Generates statistics about views.
 */
class ViewStatistics
{
    var $piwikConf;
    var $viewsConf;
    var $statsConf;

    var $date;
    var $outputDir;
    
    var $views;
    var $limit = 25;
    var $maxLimit = 1000;
    var $debug = false;
    
    /**
     * Creates a new instance of ViewStatistics.
     *
     * @param SettingsFile $settings Settings
     */
    public function __construct($settings)
    {
        $this->piwikConf = $settings['piwik'] ?? null;
        $this->viewsConf = $settings['views'] ?? null;
        $this->statsConf = $settings['statistics'] ?? null;

        if (!$this->piwikConf) {
            die("Error: missing 'piwik' settings.");
        }
        if (!$this->viewsConf) {
            die("Error: missing 'views' settings.");
        }
        if (!$this->statsConf) {
            die("Error: missing 'stats' settings.");
        }
    }

    /**
     * Returns all enabled views.
     *
     * @return array
     */
    public function getAllViews()
    {
        $base = $this->viewsConf['base_dir'] ?? null;
        if (!$base) {
            die("Error: setting 'views > base_dir' not defined.");
        }

        $baseDir = $this->viewsConf['base_dir'];
        $paths = glob("$baseDir/*/*/local/config/vufind/config.ini");
        $sites = [];
    
        foreach ($paths as $path) {
            $real = realpath($path);

            list($institution, $view, $rest)
                = explode('/', substr($path, strlen($baseDir)+1), 3);

            if ($real === false || $view === 'default') {
                continue;
            }
            
            $ini = parse_ini_file($real, true);
            
            $piwikId = $ini['Piwik']['site_id'] ?? null;
            $url = $ini['Site']['url'] ?? null;
            $enabled = $ini['System']['available'] ?? true;
            
            if (!$enabled) {
                continue;
            }
            
            $sites[] = [
                'institution' => $institution, 'view' => $view,
                'url' => $url, 'piwikId' => $piwikId
            ];
        }

        return $sites;
    }

    /**
     * Fetches statistics from Piwik and generates Excel files from them.
     *
     * @param string $date         Date range in YYYY-MM-DD,YYYY-MM-DD format
     * @param string $outputDir    Path where the Excel files will be saved
     * @param array  $institutions List of institution names to get statistics for
     * @param array  $piwikIds     List of Piwik site ids to get statistics for
     *
     * @return void
     */
    public function getStatistics(
        $date,
        $outputDir,
        $institutions = [],
        $piwikIds = []
    ) {
        $this->date = $date;
        $this->outputDir = $outputDir;
        
        $filter = function ($views, $field, $values) {
            if (empty($values)) {
                return $views;
            }
            return array_filter(
                $views,
                function ($view) use ($field, $values) {
                    return in_array($view[$field], $values);
                }
            );
        };
        
        $views = $this->getAllViews();
        $views = $filter($views, 'institution', $institutions);
        $views = $filter($views, 'piwikId', $piwikIds);

        $this->log(
            sprintf(
                'Get statistics for %d sites, date: %s:',
                count($views),
                $this->date
            )
        );
        
        foreach ($views as $view) {
            $this->log(
                sprintf(
                    '  - %s/%s (Piwik id %s)',
                    $view['institution'],
                    $view['view'],
                    $view['piwikId']
                )
            );
        }
        $this->log(PHP_EOL);
        
        $results = [];
        $statNames = array_map(
            function ($stat) {
                return $stat['label'];
            },
            $this->statsConf
        );

        $cnt = 0;
        $metadataResult = null;
        foreach ($views as $view) {
            $id = $view['piwikId'];
            $cnt++;
            $viewQueries = [];
            $metadataQueries = [];
            $statSettings = [];
            foreach ($this->statsConf as $stat) {
                if (!isset($stat['method'])) {
                    $this->log('No method defined for statistic "' . $stat['label']);
                    continue;
                }
                $statSettings[] = $stat;
                $viewQueries[] = $this->getQueryParameters(
                    $id,
                    $stat['method'],
                    $stat['limit'] ?? $this->limit
                );

                list($apiModule, $apiMethod) = explode('.', $stat['method']);
                $metadataQueries[] = $this->getMetadataQueryParameters(
                    $id,
                    $apiModule,
                    $apiMethod
                );
            }

            $this->log(
                sprintf(
                    'Fetching statistics for site %s/%s (%d of %d)',
                    $view['institution'],
                    $view['view'],
                    $cnt,
                    count($views)
                )
            );

            if ($metadataResult === null) {
                $metadataResult = $this->fetchMultiple($metadataQueries);
            }

            $queryResult = $this->fetchMultiple($viewQueries);
            $data = [];
            for ($i=0; $i<count($statSettings); $i++) {
                $metadata = json_decode($metadataResult[$i]);
                
                if (isset($metadata->result) && $metadata->result === 'error') {
                    $this->log(
                        sprintf(
                            '  error fetching metadata: %s',
                            $metadata->message
                        )
                    );
                    $metadata = $metadataResult = null;
                } else {
                    $metadata = (array) $metadata[0];
                }
                                        
                $data[] = [
                    'data' => $queryResult[$i],
                    'metadata' => $metadata,
                    'settings' => $statSettings[$i]
                ];
            }
            
            $results[$id] = array_combine(
                $statNames,
            
                $data
            );
        }
        $this->processResults($results, $views);
    }


    /**
     * Toggle debug mode.
     *
     * @param boolean $mode Debug mode.
     *
     * @return void
     */
    public function setDebug($mode)
    {
        $this->debug = $mode;
    }

    /**
     * Generate Excel files.
     *
     * @param array $results Statistics
     * @param array $views   Views
     *
     * @return void
     */
    protected function processResults($results, $views)
    {
        $viewCnt = 0;
        foreach ($results as $id => $statistics) {
            $viewCnt++;
            $output = false;
            $view = null;
            foreach ($views as $v) {
                if ($v['piwikId'] == $id) {
                    $view = $v;
                    break;
                }
            }

            $this->log(
                sprintf(
                    'Generating report for site %s/%s (%d of %d)',
                    $view['institution'],
                    $view['view'],
                    $viewCnt,
                    count($results)
                )
            );

            $sheetInfo = $this->getSheetInfo(
                $view['institution'],
                $view['view'],
                $this->date
            );

            $doc = $this->createSpreadsheet();
            $this->addCoverSheet(
                $doc,
                $view['institution'],
                $view['view'],
                $this->date,
                array_values($statistics)[0]['metadata']
            );
            $sheet = $this->addSheetInfo($doc->getActiveSheet(), $sheetInfo);

            $cnt = 1;
            
            foreach ($statistics as $name => $statisticData) {
                $data = $statisticData['data'];

                if (stripos($data, 'Error:') === 0) {
                    $this->log(
                        sprintf(
                            '  error retrieving statistics %s for view: %s/%s',
                            $name,
                            $view['institution'],
                            $view['view']
                        )
                    );
                    continue;
                }

                $output = true;
                
                $settings = $statisticData['settings'];
                $metadata = $statisticData['metadata'];
                
                $tmp = tempnam(sys_get_temp_dir(), 'csv');
                $res = file_put_contents($tmp, $data);

                $reader =  new Csv();
                $reader->setSheetIndex($cnt++);
                $reader->setInputEncoding('UTF-16');
                
                $reader->setDelimiter("\t");
                // Silence notices
                @$reader->loadIntoExisting($tmp, $doc);

                $sheet = $doc->getActiveSheet();
                $flip = !empty($settings['flip']);

                $sheet = $this->formatSheet($doc, $sheet, $metadata, $name, $flip);
                $sheet = $this->addSheetInfo($sheet, $sheetInfo);
            }

            if ($output) {
                $fname = $view['institution'];
                if ($view['view'] !== 'default') {
                    $fname .= '-' . $view['view'];
                }
                $fname = sprintf(
                    '%s/statistics-%s',
                    $this->outputDir,
                    $fname
                );
                $doc->setActiveSheetIndex(0);
                $this->saveSpreadsheet($doc, $fname);
            }
        }
    }

    /**
     * Flip statistics table rows and columns.
     *
     * @param Spreadsheet $doc   Document
     * @param Worksheet   $sheet Worksheet
     *
     * @return Flipped worksheet
     */
    protected function flipColumnsAndRows($doc, $sheet)
    {
        $newSheet = new Worksheet($doc, $sheet->getTitle());
        
        foreach (range(1, 30) as $col) {
            foreach (range(1, 30) as $row) {
                $cell = $sheet->getCellByColumnAndRow($col, $row, false);
                if ($cell) {
                    $val = $cell->getValue();
                    $newSheet->setCellValueByColumnAndRow($row, $col, $val);
                }
            }
        }
        return $newSheet;
    }

    /**
     * Crete empty spreadsheet document.
     *
     * @return Spreadsheet
     */
    protected function createSpreadsheet()
    {
        $doc = new Spreadsheet();
        return $doc;
    }

    /**
     * Add worksheet info text.
     *
     * @param Worksheet $sheet Worksheet
     * @param string    $info  Info text
     *
     * @return Worksheet
     */
    protected function addSheetInfo($sheet, $info)
    {
        $sheet->insertNewRowBefore(1, 4);
        $sheet->getCell('A1')->setValue($info);
        $sheet->mergeCells('A1:Z1');

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getStyle("A1")->applyFromArray(
            [ 'font' => [ 'bold'  =>  true ]]
        );

        return $sheet;
    }

    /**
     * Get info text for worksheet.
     *
     * @param string $institution Institution name
     * @param string $view        View name
     * @param string $period      Report period
     *
     * @return string
     */
    protected function getSheetInfo($institution, $view, $period)
    {
        list($start, $end) = explode(',', $period);
        $start = date_parse(trim($start));
        $end = date_parse(trim($end));
        $period = sprintf(
            '%d.%d.%d - %d.%d.%d',
            $start['day'],
            $start['month'],
            $start['year'],
            $end['day'],
            $end['month'],
            $end['year']
        );
        $url = "${institution}.finna.fi";
        if ($view !== 'default') {
            $url .= "/$view";
        }
        return "Statistics for view $url, period:  $period";
    }

    /**
     * Add cover sheet to report.
     *
     * @param Spreadsheet $doc         Spreadsheet
     * @param string      $institution Institution name
     * @param string      $view        View name
     * @param string      $period      Report period
     * @param array       $metadata    Metadata (documentation) for statistics
     *
     * @return void
     */
    protected function addCoverSheet($doc, $institution, $view, $period, $metadata)
    {
        $sheet = new Worksheet($doc, 'General');
        $doc->addSheet($sheet, 0);

        $documentation = [];
        if (!empty($metadata['metrics'])) {
            $documentation = (array)$metadata['metrics'];
        }
        if (!empty($metadata['metricsDocumentation'])) {
            $documentation= array_merge(
                $documentation,
                (array)$metadata['metricsDocumentation']
            );
        }

        if (!empty($documentation)) {
            $documentation
                = array_merge(['Variable name' => 'Description'], $documentation);

            $firstInd = $ind = 3;
            foreach ($documentation as $key => $val) {
                if ($ind  === $firstInd+1) {
                    $sheet->insertNewRowBefore($ind, 2);
                    $ind++;
                    $firstRow = false;
                }

                $sheet->getStyle("A${ind}")->applyFromArray(
                    [ 'font' => [ 'bold'  =>  true ]]
                );

                $sheet->getCell("A${ind}")->setValue($key);
                $sheet->getCell("B${ind}")->setValue($val);

                $ind++;
            }

            $ind += 2;
            $sheet->getCell("A${ind}")
                ->setValue('For more info see: https://glossary.matomo.org/');
        }
    }

    /**
     * Format worksheet.
     *
     * @param Spreadsheet $doc      Spreadsheet
     * @param Worksheet   $sheet    Worksheet
     * @param array       $metadata Metadata (documentation) for statistics
     * @param string      $name     Worksheet name
     * @param boolean     $flip     Flip statistics table columns with rows?
     *
     * @return Worksheet
     */
    protected function formatSheet($doc, $sheet, $metadata, $name, $flip)
    {
        $sheet->setTitle($name);

        if ($flip) {
            $sheet = $this->flipColumnsAndRows($doc, $sheet);
            $index = $doc->getActiveSheetIndex();
            $doc->removeSheetByIndex($index);
            $doc->addSheet($sheet, $index);
        }

        $maxCol = $sheet->getHighestDataColumn();
        $maxRow = $sheet->getHighestDataRow();
                
        if (!$flip) {
            // Bold header
            $sheet->getStyle("A1:${maxCol}1")->applyFromArray(
                [ 'font' => [ 'bold'  =>  true ]]
            );
        }

        // Autosize columns
        foreach (range('A', $maxCol) as $col) {
            // Autosize columns
            $sheet->getColumnDimension($col)->setAutoSize(true);

            // Right-align text in value columns
            if ($col !== 'A') {
                $cellRange = "${col}1:${col}${maxRow}";
                        
                $cells = $sheet->getStyle($cellRange);
                $cells->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        
                // Number format cells
                $cells->getNumberFormat()->setFormatCode('# ### ##0');
            }
        }
        return $sheet;
    }

    /**
     * Save spreadsheet.
     *
     * @param Spreadsheet $doc      Spreadsheet
     * @param string      $filename File name

     * @return void
     */
    protected function saveSpreadsheet($doc, $filename)
    {
        $filename .= '.xlsx';
        $writer = new Xlsx($doc);
        $writer->save($filename);
        $this->log("  Output: $filename");
    }

    /**
     * Get query parameters for Piwik API statistics query.
     *
     * @param int    $id     Piwik id
     * @param string $method Method
     * @param int    $limit  Limit
     *
     * @return array
     */
    protected function getQueryParameters($id, $method, $limit)
    {
        return [
            'method' => $method,
            'expanded' => 0,
            'filter_limit' => min([$limit, $this->maxLimit]),
            'format_metrics' => 1,
            'idSite' => $id,
            'module' => 'API',
            'date' => $this->date,
            'format' => 'TSV',
            'token_auth' => $this->piwikConf['user_token'],
            'period' => 'range',
            'language' => 'en'
        ];
    }
    
    /**
     * Get query parameters for Piwik API metadata query.
     *
     * @param int    $id     Piwik id
     * @param string $module Module
     * @param string $method Method
     *
     * @return array
     */
    protected function getMetadataQueryParameters($id, $module, $method)
    {
        return [
            'module' => 'API',
            'method' => 'API.getMetadata',
            'apiModule' => $module,
            'apiAction' => $method,
            'token_auth' => $this->piwikConf['user_token'],
            'language' => 'en',
            'idSite' => $id,
            'format' => 'json'
        ];
    }
    
    /**
     * Initializes a curl handle with the known Solr url and given parameters.
     *
     * @param string[] $params List of query parameters
     *
     * @return resource Initialized curl handle
     */
    protected function initCurl(array $params)
    {
        $curl = curl_init();

        $url = $this->piwikConf['url'];
        $url .= strpos($url, '?') !== false ? '&' : '?';
        $url .= http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        if ($this->debug) {
            $this->log('  ' . $url);
        }
        
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        return $curl;
    }

    /**
     * Uses multi threaded processing to make multiple parallel requests.
     *
     * @param array $queries Array of query parameter arrays.
     *
     * @return \SimpleXMLElement[] The xml objects from the queries
     */
    protected function fetchMultiple(array $queries)
    {
        if ($this->debug) {
            $this->log("Fetch: " . var_export($queries, true));
        }
        
        $timer = microtime(true);
        $curls = array_map([$this, 'initCurl'], $queries);

        $multi = curl_multi_init();
        array_map(
            function ($curl) use ($multi) {
                curl_multi_add_handle($multi, $curl);
            },
            $curls
        );

        $this->executeMulti($multi);
        array_map([$this, 'validateCurlResponse'], $curls);
        $results = array_map('curl_multi_getcontent', $curls);

        array_map(
            function ($curl) use ($multi) {
                curl_multi_remove_handle($multi, $curl);
            },
            $curls
        );
        curl_multi_close($multi);
        array_map('curl_close', $curls);

        if ($this->debug) {
            $this->log(
                sprintf(
                    '  Executed %d requests in %d ms',
                    count($queries),
                    (microtime(true) - $timer) * 1000
                )
            );
        }

        return $results;
    }

    /**
     * Processes the curl multi handle until the all handles are finished.
     *
     * @param resource $multi Curl multi handle to process
     *
     * @return void
     */
    protected function executeMulti($multi)
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
     * Makes sure that no errors occurred in the curl process.
     *
     * @param resource $curl The curl process to validate
     *
     * @return void
     * @throws \RuntimeException If error occurred in the curl process
     */
    protected function validateCurlResponse($curl)
    {
        if (curl_errno($curl)) {
            throw new \RuntimeException(
                'CURL error fetching from Solr: ' . curl_error($curl)
            );
        } elseif (200 !== curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
            throw new \RuntimeException(
                'Unexpected response code from Solr: '
                . curl_getinfo($curl, CURLINFO_HTTP_CODE)
            );
        }
    }

    /**
     * Outputs a message to the log.
     *
     * @param string $message Message to output
     *
     * @return void
     */
    protected function log($message)
    {
        echo $message . PHP_EOL;
    }
}
