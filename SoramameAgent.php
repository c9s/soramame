<?php
namespace Soramame;

use CLIFramework\Logger;
use CurlKit\CurlAgent;

use Symfony\Component\DomCrawler\Crawler;
use DOMElement;
use DOMText;
use RuntimeException;
use DateTime;
use DateTimeZone;

class IncorrectDataException extends RuntimeException
{
    public $url;

    public $data;

    public $html;

    public function __construct($message, $url, $data = array(), $html = NULL)
    {
        parent::__construct($message);
        $this->url = $url;
        $this->data = $data;
        $this->html = $html;
    }

    public function getData() {
        return $this->data;
    }

    public function getUrl() {
        return $this->url;
    }
}


class SoramameAgent
{
    const REQUEST_DELAY = 1000000; // 1 second

    const BASE_URL = 'http://soramame.taiki.go.jp';

    const PROVINCE_LIST_PAGE = '/MstItiran.php';

    const STATION_LIST_PAGE = '/MstItiranHyou.php?Pref={city}&Time={time}';

    const STATION_TITLE_PAGE = '/MstItiranTitle.php?Time={time}';

    const MEASUREMENT_TITLE_PAGE = '/DataListTitle.php?MstCode={code}&Time={time}';

    const MEASUREMENT_DATA_PAGE = '/DataListHyou.php?MstCode={code}&Time={time}';

    public $logger;

    public $agent;

    public function __construct(CurlAgent $agent, Logger $logger) {
        $this->agent = $agent;
        $this->agent->userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2328.0 Safari/537.36';
        $this->logger = $logger;
    }

    public function getCountyListPageUrl() {
        return self::BASE_URL . self::PROVINCE_LIST_PAGE;
    }

    public function getStationTitlePageUrl() {
        return self::BASE_URL . 
            str_replace(['{time}' ], [date('YmdH') ], 
                self::STATION_TITLE_PAGE);
    }

    public function getStationListPageUrl($cityId) {
        return self::BASE_URL . 
            str_replace([ '{city}', '{time}' ], [ $cityId, date('YmdH') ], 
                self::STATION_LIST_PAGE);
    }

    public function getMeasurementTitlePageUrl($code) {
        return self::BASE_URL . 
            str_replace([ '{code}', '{time}' ], [ $code, date('YmdH') ], 
                self::MEASUREMENT_TITLE_PAGE);
    }

    public function getMeasurementDataPageUrl($code)
    {
        return self::BASE_URL . 
            str_replace([ '{code}', '{time}' ], [ $code, date('YmdH') ], 
                self::MEASUREMENT_DATA_PAGE);
    }

    public function fetchCountyStations($countyId, array $attributeNames)
    {
        $pageUrl = $this->getStationListPageUrl($countyId);

        $this->logger->info("Fetching country station list: $pageUrl");

        $response = $this->agent->get($pageUrl);
        $pageHtml = $response->decodeBody();

        $dataCrawler = new Crawler($pageHtml);
        $countyStationRows = $dataCrawler->filter('table.hyoMenu tr');
        $stations = [];
        foreach ($countyStationRows as $countyStationRow) {
            $stationInfo = $this->parseCountyStationRow($countyStationRow);
            if (empty($stationInfo)) {
                $this->logger->warn('Empty station info, row: ' . $countyStationRow->C14N());
                continue;
            }

            if (count($attributeNames) == count($stationInfo['attributes'])) {
                $stationInfo['attributes'] = array_combine($attributeNames, $stationInfo['attributes']);
            } else {
                throw new IncorrectDataException('Inequal attribute numbers', $pageUrl, $stationInfo, $countyStationRow->C14N());
            }

            if (!$stationInfo['code'] && !$stationInfo['name'] && !$stationInfo['address']) {
                // Empty row sometimes appear in the table....... errr
                // $this->logger->warn('Empty row found');
                // $this->logger->warn('Station info not found in: ' . $countyStationRow->C14N());
                continue;
            }
            $stations[] = $stationInfo;
        }
        return $stations;
    }

    protected function parseCountyStationRow(DOMElement $row) {
        $rowCrawler = new Crawler($row);
        $columnElements = $row->getElementsByTagName('td');
        $mstCode = $columnElements->item(0)->textContent;
        $mstName = $columnElements->item(1)->textContent;
        $mstAddress = $columnElements->item(2)->textContent;

        $attributes = array();
        $supportedAttributeColumns = $rowCrawler->filter('.MstHyo_Co, .MstHyo');
        foreach($supportedAttributeColumns as $attrbuteColumn) {
            if (preg_match('/○/',$attrbuteColumn->textContent)) {
                $attributes[] = true;
            } elseif (preg_match('/×/',$attrbuteColumn->textContent)) {
                $attributes[] = false;
            } else {
                $attributes[] = null;
            }
        }
        return [
            'code' => trim($mstCode),
            'name' => trim($mstName),
            'address' => trim($mstAddress),
            'attributes' => $attributes,
        ];
    }

    protected function buildAttributeTable($html)
    {
        $attributeCrawler = new Crawler($html);
        $attributeRow = $attributeCrawler->filter('.hyoMenu .hyoMenu_Komoku')->eq(1)->getNode(0);
        // print_r($attributeRow);

        $attributeNames = [];
        $children = $attributeRow->childNodes;
        foreach($children as $item) {
            if ($item instanceof DOMText) {
                continue;
            }
            $span = $item->childNodes->item(0);
            $attributeNames[] = trim($span->textContent);
        }
        return $attributeNames;
    }

    public function fetchStationAttributes()
    {
        $this->logger->info('Parsing station attributes ' . $this->getStationTitlePageUrl());
        $response = $this->agent->get($this->getStationTitlePageUrl());
        return $this->buildAttributeTable($response->decodeBody());
    }

    public function fetchCountyList()
    {
        $this->logger->info('Fetching county list ' . $this->getCountyListPageUrl());
        $crawler = new Crawler(file_get_contents($this->getCountyListPageUrl()));
        $crawler = $crawler->filter('.DataKoumoku select[name="ListPref"] option');
        $counties = array();
        foreach($crawler as $i => $countyOption) {
            $countyId = $countyOption->getAttribute('value');
            if ($countyId == 0) {
                continue;
            }
            $countyName = trim($countyOption->textContent);
            $counties[ $countyId ] = $countyName;
        }
        return $counties;
    }

    public function fetchStationHistory($code)
    {
        $timestamp = date('Ymdh');

        // Parse measurement header of the station
        $response = $this->agent->get($this->getMeasurementTitlePageUrl($code));
        $html = $response->decodeBody();

        // $html = file_get_contents('japan/DataListTitle.php?MstCode=44201010&Time=2015031517');

        $crawler = new Crawler($html);
        $titleTable = $crawler->filter('table.hyoMenu')->eq(1); // get the second table
        $titleRows = $titleTable->children();
        $elementRow = $titleRows->eq(0);
        $elementCells = $elementRow->children();

        $unitRow = $titleRows->eq(1);
        $unitCells = $unitRow->children();


        $functionalRow = $titleRows->eq(2);
        $functionalCells = $functionalRow->children();


        $labels = [];
        foreach($elementCells as $index => $cell) {
            // Skip text nodes
            if ($cell instanceof DOMText) {
                continue;
            }
            $labels[] = $cell->textContent;
        } 
        array_splice($labels, 0, 4); // year, month, day, hour
        $labels = array_map('strtolower', $labels); // transform text to lower

        $units = [];
        foreach($unitCells as $cell) {
            if ($cell instanceof DOMText) {
                continue;
            }
            $units[] = $cell->textContent;
        }

        // Align the rowspan for units
        foreach($elementCells as $index => $cell) {
            if ($cell instanceof DOMText) {
                continue;
            }
            $rowspan = intval($cell->getAttribute("rowspan"));
            if ($rowspan > 1) {
                $this->logger->debug("Make a room from index $index => $rowspan");
                array_splice($units, $index, 0, ['_']);
            }
        }
        array_splice($units, 0 , 4);
        $labelUnits = array_combine($labels, $units);

        // Parse measurement body
        $this->logger->info('Fetching ' . $this->getMeasurementDataPageUrl($code));
        $response = $this->agent->get($this->getMeasurementDataPageUrl($code));
        $html = $response->decodeBody();

        // $html = mb_convert_encoding($html, 'utf-8', 'EUC-JP');
        $crawler = new Crawler($html);
        $crawler = $crawler->filter('table.hyoMenu tr');

        $this->logger->info("Found " . $crawler->count() .  " records.");

        $results = [];
        foreach ($crawler as $row) {
            // Normalize the data
            $cells = $row->childNodes;
            $rowContents = [];
            foreach ($cells as $cell) {
                // Skip text nodes
                if ($cell instanceof DOMText) {
                    continue;
                }
                $rowContents[] = $cell->textContent;
            }
            $year = array_shift($rowContents);
            $month = array_shift($rowContents);
            $day = array_shift($rowContents);
            $hour = array_shift($rowContents);

            // Create measurement DateTime object from the table cell text.
            $minute = 0;
            $dateTime = new DateTime();
            $dateTime->setTimeZone(new DateTimeZone('Asia/Tokyo'));
            $dateTime->setDate( intval($year), intval($month), intval($day) );
            $dateTime->setTime( intval($hour), $minute);
            // $this->logger->info("Measurement time: " . $dateTime->format(DateTime::ATOM));
            
            // Merge and filter the resutls
            $measureData = array_combine($labels, $rowContents);
            $measureData = array_filter($measureData, 'is_numeric');
            $measureData = array_map('doubleval', $measureData);
            $measureData = array_merge($measureData, [
                'published_at' => $dateTime->format(DateTime::ATOM),
            ]);
            $results[] = $measureData;
        }
        return $results;
    }
}
