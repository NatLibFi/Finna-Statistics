<?php
$url = 'https://solr.finna.fi/solr/biblio/select';  

date_default_timezone_set('UTC');
$date = date('c');

function buildFilters($filterNames = array()) {
  $filterQueries['nonautomated'] = '-merged_boolean:TRUE';
  $filterQueries['online'] = 'online_str_mv:*';

   $filters = array();
   foreach($filterNames as $filterName) {
       $filters[] = $filterQueries[$filterName];
   }
   
   $filterString = 'fq=';
   $filterString .= implode('%20AND%20', $filters);
   
   return $filterString;
}

function fatalError($message) {
    die('FinnaError (index statistics): ' . $message . "\n");
}

function solrQuery($query) {
    global $url;
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url . '?' . $query);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    if(curl_errno($curl)) {
        fatalError('Could not reach Solr: ' . curl_error($curl));
    } elseif (200 !== curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
        fatalError('Could not read Solr data');
    }
    curl_close($curl);
    $xmlObject = new SimpleXMLElement($result);
    
    if(NULL === $xmlObject->result['numFound']) {
        fatalError('Cannot determine amount of records');
    }
    
    return $xmlObject;
}

function getResultAmount($query) {
    $xmlObject = solrQuery($query);

    return $xmlObject->result['numFound'];  
}

function getFormatQueries() {
    $xmlObject = solrQuery('q=*:*&rows=0&facet=true&facet.field=format&facet.prefix=0');

    $formatQueries = array();
    foreach($xmlObject->xpath('//lst[@name="format"]/int') as $formatItem) {
        $formatQueries[] = 'format:"' . urlencode((string) $formatItem['name']) . '"';
    }
            
    return $formatQueries;  
}

function getSectorQueries() {
    $xmlObject = solrQuery('q=*:*&rows=0&facet=true&facet.field=sector_str_mv');
  
    $sectorQueries = array();
    foreach($xmlObject->xpath('//lst[@name="sector_str_mv"]/int') as $sectorItem) {
        $sectorQueries[] = 'sector_str_mv:"' . urlencode((string) $sectorItem['name']) . '"';
    }
            
    return $sectorQueries;  
}

$queries = array('*:*');

$queries = array_merge($queries, getSectorQueries());
$queries = array_merge($queries, getFormatQueries());

ob_start();

foreach ($queries as $i) {
    $i = 'q=' . $i;
    $fullQueries = array();
    $fullQueries['all']['query'] = $i;
    $fullQueries['nonautomated']['query'] = $i;
    $fullQueries['online']['query'] = $i;
    $fullQueries['online-nonautomated']['query'] = $i;
    $fullQueries['nonautomated']['query'] .= '&' . buildFilters(array('nonautomated'));
    $fullQueries['online']['query'] .= '&' . buildFilters(array('online'));
    $fullQueries['online-nonautomated']['query'] .= '&' . buildFilters(array('online', 'nonautomated'));

    foreach ($fullQueries as & $query) {
        $query['result'] = getResultAmount($query['query']);
    }

    unset($query); // Kill the loose pointer

    $row = array();
    
    $row[] = $date;
    $row[] = urldecode($i);
    foreach($fullQueries as $query) {
        $row[] = $query['result'];
        error_log($query['query']);
    }
/*    $row[] = round(($fullQueries['all']['result'] - $fullQueries['nonautomated']['result']) / $fullQueries['all']['result'] * 100) . '%';
    $row[] = round($fullQueries['online']['result'] / $fullQueries['all']['result'] * 100) . '%'; */

    $fp = fopen('php://output', 'w');
    fputcsv($fp, $row);
    fclose($fp);
}

$output = ob_get_clean();

$filename = !empty($argv[1]) ? $argv[1] : 'stats.csv';
$success = file_put_contents($filename, $output, FILE_APPEND);
if (!$success) {
    fatalError("Error in writing to file: {$argv[1]}");
}

?>
