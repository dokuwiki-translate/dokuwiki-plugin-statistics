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

    /**
     * Analyze a referrer URL to extract search engine information and query
     *
     * @param string $referer The HTTP referer URL
     * @return array|null Array with 'engine', 'name', 'query' keys or null if not a search engine
     */
    public function analyzeReferrer(string $referer): ?array
    {
        $referer = strtolower($referer);
        
        // parse the referer
        $urlparts = parse_url($referer);
        if (!isset($urlparts['host'])) {
            return null;
        }
        
        $domain = $urlparts['host'];
        $qpart = $urlparts['query'] ?? '';
        if (!$qpart && isset($urlparts['fragment'])) {
            $qpart = $urlparts['fragment']; // google does this
        }

        $params = [];
        if ($qpart) {
            parse_str($qpart, $params);
        }

        $query = '';
        $engineKey = '';
        $engineName = '';

        // check domain against known search engines
        foreach ($this->searchEngines as $key => $engine) {
            if (!$engine['regex']) continue; // skip engines without regex (like dokuwiki)
            
            if (preg_match('/' . $engine['regex'] . '/', $domain)) {
                $engineKey = $key;
                $engineName = $engine['name'];
                
                // check the known parameters for content
                foreach ($engine['params'] as $param) {
                    if (!empty($params[$param])) {
                        $query = $params[$param];
                        break;
                    }
                }
                break;
            }
        }

        // try some generic search engine parameters if no specific engine matched
        if (!$engineKey) {
            foreach (['search', 'query', 'q', 'keywords', 'keyword'] as $param) {
                if (!empty($params[$param])) {
                    $query = $params[$param];
                    // generate name from domain
                    $engineName = preg_replace('/(\.co)?\.([a-z]{2,5})$/', '', $domain); // strip tld
                    $engineName = explode('.', $engineName);
                    $engineName = array_pop($engineName);
                    $engineKey = 'generic_' . $engineName;
                    break;
                }
            }
        }

        // still no hit? not a search engine
        if (!$engineKey || !$query) {
            return null;
        }

        // clean the query
        $query = preg_replace('/^(cache|related):[^\+]+/', '', $query); // non-search queries
        $query = preg_replace('/ +/', ' ', $query); // ws compact
        $query = trim($query);
        
        if (!$query) {
            return null;
        }

        return [
            'engine' => $engineKey,
            'name' => $engineName,
            'query' => $query
        ];
    }

    /**
     * Get search engine information by key
     *
     * @param string $key The search engine key
     * @return array|null The search engine data or null if not found
     */
    public function getSearchEngine(string $key): ?array
    {
        return $this->searchEngines[$key] ?? null;
    }

    /**
     * Get all search engines
     *
     * @return array All search engine definitions
     */
    public function getAllSearchEngines(): array
    {
        return $this->searchEngines;
    }

}
