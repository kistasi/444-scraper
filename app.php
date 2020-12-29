<?php
require 'vendor/autoload.php';

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

const MAIN_URL = 'https://444.hu/';
const AUTHORS = [
    'acsd', 'bedem', 'borosj', 'botost', 'csurgod', 'czinkoczis', 'erdelyip', 'haszanz', 'herczegm', 'horvathb',
    'akiraly', 'magyarip', 'winkler', 'neubergere', 'plankog', 'renyip', 'sarkadizs', 'szaszzs', 'szily', 'szuroveczi',
    'tbg', 'peteru', 'urfip', 'vajdag'
];

function main()
{
    $data = scrape();
    makeExcelFromData($data);
}

function scrape()
{
    $client = new Client();
    $data = [];

    foreach (AUTHORS as $author) {
        scraperLog('Author: ' . $author);

        $articlesInPage = true;
        $page = 1;

        while ($articlesInPage) {
            $authorPageUrl = MAIN_URL . 'author/' . $author . '?page=' . $page;

            scraperLog('Author page URL: ' . $authorPageUrl);

            $author_page = $client->request('GET', $authorPageUrl);
            $articles = $author_page->filter('.card h3 a');

            if ($articles->count() === 0) {
                $articlesInPage = false;
            } else {
                $page++;
            }

            $articles->each(function (Crawler $node) use ($client, &$data) {
                $articleUrl = $node->attr('href');

                scraperLog('Article URL: ' . $articleUrl);

                $articlePage = $client->request('GET', $articleUrl);

                $data[] = [
                    getTextBySelector($articlePage, 'h1'),
                    extractDateFromUrl($articleUrl),
                    getTextBySelector($articlePage, '.byline__info .byline__authors a'),
                    getTextBySelector($articlePage, '.byline__info .byline__category'),
                    getTextBySelector($articlePage, '.byline__info .share-count'),
                    $articleUrl
                ];
            });
        }
    }

    return $data;
}

function extractDateFromUrl(string $url): string
{
    $date = explode('/', $url);
    return $date[3] . '-' . $date[4] . '-' . $date[5];
}

function getTextBySelector(Crawler $node, string $selector): string
{
    try {
        return $node->filter($selector)->first()->text();
    } catch (\Exception $e) {
        return '-';
    }
}

function makeExcelFromData(array $data)
{
    if (!file_exists('data')) {
        mkdir('data');
    }

    $headingRow = ['Title', 'Date', 'Author', 'Category', 'Share', 'URL'];
    array_unshift($data, $headingRow);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($data);

    $writer = new Xlsx($spreadsheet);
    $file_name = 'data/articles-' . time() . '.xlsx';
    $writer->save($file_name);
}

function scraperLog(string $message)
{
    echo $message . PHP_EOL;
}

main();
