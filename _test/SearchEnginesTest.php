<?php

namespace dokuwiki\plugin\statistics\test;

use DokuWikiTest;
use dokuwiki\plugin\statistics\SearchEngines;

/**
 * Tests for the SearchEngines class
 *
 * @group plugin_statistics
 * @group plugins
 */
class SearchEnginesTest extends DokuWikiTest
{
    /**
     * Data provider for testing known search engines
     */
    public function knownSearchEnginesProvider(): array
    {
        return [
            // Google variants
            'google.com' => [
                'https://www.google.com/search?q=dokuwiki+test',
                true,
                'google',
                'Google',
                'dokuwiki test'
            ],
            'google.co.uk' => [
                'https://www.google.co.uk/search?q=php+framework',
                true,
                'google',
                'Google',
                'php framework'
            ],
            'google.de' => [
                'https://www.google.de/search?q=test+query',
                true,
                'google',
                'Google',
                'test query'
            ],

            // Bing
            'bing.com' => [
                'https://www.bing.com/search?q=dokuwiki+plugin',
                true,
                'bing',
                'Bing',
                'dokuwiki plugin'
            ],
            'bing.co.uk' => [
                'https://www.bing.co.uk/search?q=search+test',
                true,
                'bing',
                'Bing',
                'search test'
            ],

            // Yahoo
            'yahoo.com' => [
                'https://search.yahoo.com/search?p=test+search',
                true,
                'yahoo',
                'Yahoo!',
                'test search'
            ],

            // Yandex
            'yandex.ru' => [
                'https://yandex.ru/search/?query=test+query',
                true,
                'yandex',
                'Яндекс (Yandex)',
                'test query'
            ],
            'yandex.com' => [
                'https://yandex.com/search/?query=another+test',
                true,
                'yandex',
                'Яндекс (Yandex)',
                'another test'
            ],

            // Naver
            'naver.com' => [
                'https://search.naver.com/search.naver?query=korean+search',
                true,
                'naver',
                '네이버 (Naver)',
                'korean search'
            ],

            // Baidu
            'baidu.com' => [
                'https://www.baidu.com/s?wd=chinese+search',
                true,
                'baidu',
                '百度 (Baidu)',
                'chinese search'
            ],
            'baidu.com word param' => [
                'https://www.baidu.com/s?word=test+word',
                true,
                'baidu',
                '百度 (Baidu)',
                'test word'
            ],
            'baidu.com kw param' => [
                'https://www.baidu.com/s?kw=keyword+test',
                true,
                'baidu',
                '百度 (Baidu)',
                'keyword test'
            ],

            // Ask
            'ask.com' => [
                'https://www.ask.com/web?q=ask+search',
                true,
                'ask',
                'Ask',
                'ask search'
            ],
            'ask.com ask param' => [
                'https://www.ask.com/web?ask=test+ask',
                true,
                'ask',
                'Ask',
                'test ask'
            ],
            'search-results.com' => [
                'https://www.search-results.com/web?q=search+results',
                true,
                'ask_search_results',
                'Ask',
                'search results'
            ],

            // DuckDuckGo
            'duckduckgo.com' => [
                'https://duckduckgo.com/?q=privacy+search',
                true,
                'duckduckgo',
                'DuckDuckGo',
                'privacy search'
            ],

            // Ecosia
            'ecosia.org' => [
                'https://www.ecosia.org/search?method=index&q=eco+friendly+search',
                true,
                'ecosia',
                'Ecosia',
                'eco friendly search'
            ],

            // Qwant
            'qwant.com' => [
                'https://www.qwant.com/?q=dokuwiki&t=web',
                true,
                'qwant',
                'Qwant',
                'dokuwiki'
            ],

            // AOL
            'aol.com' => [
                'https://search.aol.com/aol/search?query=aol+search',
                true,
                'aol',
                'AOL Search',
                'aol search'
            ],

            'aol.co.uk' => [
                'https://search.aol.co.uk/aol/search?q=uk+search',
                true,
                'aol',
                'AOL Search',
                'uk search'
            ],

            // Babylon
            'babylon.com' => [
                'https://search.babylon.com/?q=babylon+search',
                true,
                'babylon',
                'Babylon',
                'babylon search'
            ],

            // Google AVG
            'avg.com' => [
                'https://search.avg.com/search?q=avg+search',
                true,
                'google_avg',
                'Google',
                'avg search'
            ],
        ];
    }

    /**
     * Data provider for testing generic search engines
     */
    public function genericSearchEnginesProvider(): array
    {
        return [
            'generic with q param' => [
                'https://search.example.com/?q=generic+search',
                true,
                'example',
                'Example',
                'generic search'
            ],
            'generic with query param' => [
                'https://find.testsite.org/search?query=test+query',
                true,
                'testsite',
                'Testsite',
                'test query'
            ],
            'generic with search param' => [
                'https://www.searchengine.net/?search=search+term',
                true,
                'searchengine',
                'Searchengine',
                'search term'
            ],
            'generic with keywords param' => [
                'https://lookup.site.com/?keywords=keyword+test',
                true,
                'site',
                'Site',
                'keyword test'
            ],
            'generic with keyword param' => [
                'https://engine.co.uk/?keyword=single+keyword',
                true,
                'engine',
                'Engine',
                'single keyword'
            ],
        ];
    }

    /**
     * Data provider for testing non-search engine referers
     */
    public function nonSearchEngineProvider(): array
    {
        return [
            'regular website' => [
                'https://www.example.com/page',
                false,
                null,
                null,
                null
            ],
            'social media' => [
                'https://www.facebook.com/share',
                false,
                null,
                null,
                null
            ],
            'invalid URL' => [
                'not-a-url',
                false,
                null,
                null,
                null
            ],
            'URL without host' => [
                '/local/path',
                false,
                null,
                null,
                null
            ],
        ];
    }

    /**
     * Data provider for testing query cleaning
     */
    public function queryCleaningProvider(): array
    {
        return [
            'cache query removed' => [
                'https://www.google.com/search?q=cache:example.com+test',
                true,
                'google',
                'Google',
                'test'
            ],
            'related query removed' => [
                'https://www.google.com/search?q=related:example.com+search',
                true,
                'google',
                'Google',
                'search'
            ],
            'multiple spaces compacted' => [
                'https://www.google.com/search?q=test++multiple+++spaces',
                true,
                'google',
                'Google',
                'test multiple spaces'
            ],
            'whitespace trimmed' => [
                'https://www.google.com/search?q=++trimmed++',
                true,
                'google',
                'Google',
                'trimmed'
            ],
        ];
    }

    /**
     * Data provider for testing fragment-based queries
     */
    public function fragmentQueryProvider(): array
    {
        return [
            'fragment query' => [
                'https://www.google.com/search#q=fragment+query',
                true,
                'google',
                'Google',
                'fragment query'
            ],
            'fragment with multiple params' => [
                'https://www.bing.com/search#q=fragment+test&other=param',
                true,
                'bing',
                'Bing',
                'fragment test'
            ],
        ];
    }

    /**
     * Test known search engines
     * @dataProvider knownSearchEnginesProvider
     */
    public function testKnownSearchEngines(
        string $referer,
        bool $expectedIsSearchEngine,
        ?string $expectedEngine,
        ?string $expectedName,
        ?string $expectedQuery
    ): void {
        $searchEngine = new SearchEngines($referer);

        $this->assertEquals($expectedIsSearchEngine, $searchEngine->isSearchEngine());
        $this->assertEquals($expectedEngine, $searchEngine->getEngine());
        $this->assertEquals($expectedQuery, $searchEngine->getQuery());

        if ($expectedEngine) {
            $this->assertEquals($expectedName, SearchEngines::getName($expectedEngine));
        }
    }

    /**
     * Test generic search engines
     * @dataProvider genericSearchEnginesProvider
     */
    public function testGenericSearchEngines(
        string $referer,
        bool $expectedIsSearchEngine,
        ?string $expectedEngine,
        ?string $expectedName,
        ?string $expectedQuery
    ): void {
        $searchEngine = new SearchEngines($referer);

        $this->assertEquals($expectedIsSearchEngine, $searchEngine->isSearchEngine());
        $this->assertEquals($expectedEngine, $searchEngine->getEngine());
        $this->assertEquals($expectedQuery, $searchEngine->getQuery());

        if ($expectedEngine) {
            $this->assertEquals($expectedName, SearchEngines::getName($expectedEngine));
        }
    }

    /**
     * Test non-search engine referers
     * @dataProvider nonSearchEngineProvider
     */
    public function testNonSearchEngines(
        string $referer,
        bool $expectedIsSearchEngine,
        ?string $expectedEngine,
        ?string $expectedName,
        ?string $expectedQuery
    ): void {
        $searchEngine = new SearchEngines($referer);

        $this->assertEquals($expectedIsSearchEngine, $searchEngine->isSearchEngine());
        $this->assertEquals($expectedEngine, $searchEngine->getEngine());
        $this->assertEquals($expectedQuery, $searchEngine->getQuery());
    }

    /**
     * Test query cleaning functionality
     * @dataProvider queryCleaningProvider
     */
    public function testQueryCleaning(
        string $referer,
        bool $expectedIsSearchEngine,
        ?string $expectedEngine,
        ?string $expectedName,
        ?string $expectedQuery
    ): void {
        $searchEngine = new SearchEngines($referer);

        $this->assertEquals($expectedIsSearchEngine, $searchEngine->isSearchEngine());
        $this->assertEquals($expectedEngine, $searchEngine->getEngine());
        $this->assertEquals($expectedQuery, $searchEngine->getQuery());
    }

    /**
     * Test fragment-based queries
     * @dataProvider fragmentQueryProvider
     */
    public function testFragmentQueries(
        string $referer,
        bool $expectedIsSearchEngine,
        ?string $expectedEngine,
        ?string $expectedName,
        ?string $expectedQuery
    ): void {
        $searchEngine = new SearchEngines($referer);

        $this->assertEquals($expectedIsSearchEngine, $searchEngine->isSearchEngine());
        $this->assertEquals($expectedEngine, $searchEngine->getEngine());
        $this->assertEquals($expectedQuery, $searchEngine->getQuery());
    }

    /**
     * Test static getName method with unknown engine
     */
    public function testGetNameUnknownEngine(): void
    {
        $unknownEngine = 'unknown_engine';
        $this->assertEquals('Unknown_engine', SearchEngines::getName($unknownEngine));
    }

    /**
     * Test static getUrl method
     */
    public function testGetUrl(): void
    {
        $this->assertEquals('http://www.google.com', SearchEngines::getUrl('google'));
        $this->assertEquals('http://www.bing.com', SearchEngines::getUrl('bing'));
        $this->assertNull(SearchEngines::getUrl('unknown_engine'));
    }

    /**
     * Test DokuWiki internal search detection
     */
    public function testDokuWikiInternalSearch(): void
    {
        // Mock DOKU_URL for testing
        if (!defined('DOKU_URL')) {
            define('DOKU_URL', 'https://wiki.example.com/');
        }

        $referer = 'https://wiki.example.com/doku.php?do=search&q=internal+search';
        $searchEngine = new SearchEngines($referer);

        $this->assertTrue($searchEngine->isSearchEngine());
        $this->assertEquals('dokuwiki', $searchEngine->getEngine());
        $this->assertEquals('internal search', $searchEngine->getQuery());
        $this->assertEquals('DokuWiki Internal Search', SearchEngines::getName('dokuwiki'));
    }

    /**
     * Test case insensitive domain matching
     */
    public function testCaseInsensitiveDomainMatching(): void
    {
        $referer = 'https://WWW.GOOGLE.COM/search?q=case+test';
        $searchEngine = new SearchEngines($referer);

        $this->assertTrue($searchEngine->isSearchEngine());
        $this->assertEquals('google', $searchEngine->getEngine());
        $this->assertEquals('case test', $searchEngine->getQuery());
    }

    /**
     * Test URL encoding in queries
     */
    public function testUrlEncodedQueries(): void
    {
        $referer = 'https://www.google.com/search?q=url%20encoded%20query';
        $searchEngine = new SearchEngines($referer);

        $this->assertTrue($searchEngine->isSearchEngine());
        $this->assertEquals('google', $searchEngine->getEngine());
        $this->assertEquals('url encoded query', $searchEngine->getQuery());
    }

    /**
     * Test plus encoding in queries
     */
    public function testPlusEncodedQueries(): void
    {
        $referer = 'https://www.google.com/search?q=plus+encoded+query';
        $searchEngine = new SearchEngines($referer);

        $this->assertTrue($searchEngine->isSearchEngine());
        $this->assertEquals('google', $searchEngine->getEngine());
        $this->assertEquals('plus encoded query', $searchEngine->getQuery());
    }

    /**
     * Test empty constructor behavior
     */
    public function testEmptyReferer(): void
    {
        $searchEngine = new SearchEngines('');

        $this->assertFalse($searchEngine->isSearchEngine());
        $this->assertNull($searchEngine->getEngine());
        $this->assertNull($searchEngine->getQuery());
    }
}
