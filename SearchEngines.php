<?php

namespace dokuwiki\plugin\statistics;

/**
 * Defines regular expressions for the most common search engines
 */
class SearchEngines
{
    /** @var array Search engine definitions with regex patterns and metadata */
    protected array $searchEngines = [
        'google' => [
            'name' => 'Google',
            'url' => 'http://www.google.com',
            'regex' => '^(\w+\.)*google(\.co)?\.([a-z]{2,5})$',
            'params' => ['q']
        ],
        'bing' => [
            'name' => 'Bing',
            'url' => 'http://www.bing.com',
            'regex' => '^(\w+\.)*bing(\.co)?\.([a-z]{2,5})$',
            'params' => ['q']
        ],
        'yandex' => [
            'name' => 'Яндекс (Yandex)',
            'url' => 'http://www.yandex.ru',
            'regex' => '^(\w+\.)*yandex(\.co)?\.([a-z]{2,5})$',
            'params' => ['query']
        ],
        'yahoo' => [
            'name' => 'Yahoo!',
            'url' => 'http://www.yahoo.com',
            'regex' => '^(\w+\.)*yahoo\.com$',
            'params' => ['p']
        ],
        'naver' => [
            'name' => '네이버 (Naver)',
            'url' => 'http://www.naver.com',
            'regex' => '^search\.naver\.com$',
            'params' => ['query']
        ],
        'baidu' => [
            'name' => '百度 (Baidu)',
            'url' => 'http://www.baidu.com',
            'regex' => '^(\w+\.)*baidu\.com$',
            'params' => ['wd', 'word', 'kw']
        ],
        'ask' => [
            'name' => 'Ask',
            'url' => 'http://www.ask.com',
            'regex' => '^(\w+\.)*ask\.com$',
            'params' => ['ask', 'q', 'searchfor']
        ],
        'ask_search_results' => [
            'name' => 'Ask',
            'url' => 'http://www.ask.com',
            'regex' => '^(\w+\.)*search-results\.com$',
            'params' => ['ask', 'q', 'searchfor']
        ],
        'babylon' => [
            'name' => 'Babylon',
            'url' => 'http://search.babylon.com',
            'regex' => '^search\.babylon\.com$',
            'params' => ['q']
        ],
        'aol' => [
            'name' => 'AOL Search',
            'url' => 'http://search.aol.com',
            'regex' => '^(\w+\.)*(aol)?((search|recherches?|images|suche|alicesuche)\.)aol(\.co)?\.([a-z]{2,5})$',
            'params' => ['query', 'q']
        ],
        'duckduckgo' => [
            'name' => 'DuckDuckGo',
            'url' => 'http://duckduckgo.com',
            'regex' => '^duckduckgo\.com$',
            'params' => ['q']
        ],
        'google_avg' => [
            'name' => 'Google',
            'url' => 'http://www.google.com',
            'regex' => '^search\.avg\.com$',
            'params' => ['q']
        ]
    ];

    public function __construct()
    {
        // Add the internal DokuWiki search engine
        $this->searchEngines['dokuwiki'] = [
            'name' => 'DokuWiki Internal Search',
            'url' => wl(),
            'regex' => '',
            'params' => ['q']
        ];
    }

}
