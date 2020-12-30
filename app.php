<?php
require 'vendor/autoload.php';

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

const BASE_URL = 'https://444.hu/';

function main()
{
    $authors = scrapeAuthors();
    $authors = modifyAuthors($authors);

    $articles = scrapeArticles($authors);
    $articles = filterArticleDuplications($articles);

    makeExcelFromData($articles);
}

function scrapeAuthors(): array
{
    $client = new Client();
    $authors = [];

    $authors_url = BASE_URL . 'impresszum';
    $authors_page = $client->request('GET', $authors_url);
    $authors_page->filter('a[title="Cikkek"]')->each(function (Crawler $node) use ($client, &$authors) {
        $authors[] = explode('/', $node->attr('href'))[4];
    });

    return $authors;
}

function modifyAuthors(array $authors): array
{
    unset($authors[array_search('hirdetes', $authors)]);
    $authors[] = 'winkler';

    return $authors;
}

function scrapeArticles(array $authors): array
{
    $client = new Client();
    $data = [];

    foreach ($authors as $author) {
        scraperLog('Author: ' . $author);

        $articlesInPage = true;
        $page = 1;

        while ($articlesInPage) {
            $authorPageUrl = BASE_URL . 'author/' . $author . '?page=' . $page;

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

function filterArticleDuplications(array $articles): array
{
    return $articles;
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
