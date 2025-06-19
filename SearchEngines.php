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

    /** @var string|null The referrer URL being analyzed */
    protected ?string $referrer = null;
    
    /** @var array|null Cached analysis result */
    protected ?array $analysisResult = null;

    public function __construct(?string $referrer = null)
    {
        // Add the internal DokuWiki search engine
        $this->searchEngines['dokuwiki'] = [
            'name' => 'DokuWiki Internal Search',
            'url' => wl(),
            'regex' => '',
            'params' => ['q']
        ];
        
        if ($referrer !== null) {
            $this->referrer = $referrer;
        }
    }

    /**
     * Check if the referrer is from a search engine
     *
     * @return bool True if the referrer is from a search engine
     */
    public function isSearchEngine(): bool
    {
        return $this->getAnalysis() !== null;
    }

    /**
     * Get the search engine name
     *
     * @return string|null The search engine name or null if not a search engine
     */
    public function getName(): ?string
    {
        $analysis = $this->getAnalysis();
        return $analysis['name'] ?? null;
    }

    /**
     * Get the search engine URL
     *
     * @return string|null The search engine URL or null if not a search engine
     */
    public function getUrl(): ?string
    {
        $analysis = $this->getAnalysis();
        if (!$analysis) {
            return null;
        }
        
        return $this->searchEngines[$analysis['engine']]['url'] ?? null;
    }

    /**
     * Get the search query
     *
     * @return string|null The search query or null if not a search engine
     */
    public function getQuery(): ?string
    {
        $analysis = $this->getAnalysis();
        return $analysis['query'] ?? null;
    }

    /**
     * Get or perform analysis of the current referrer
     *
     * @return array|null Analysis result or null if not a search engine
     */
    protected function getAnalysis(): ?array
    {
        if ($this->analysisResult === null && $this->referrer !== null) {
            $this->analysisResult = $this->analyzeReferrer($this->referrer);
        }
        
        return $this->analysisResult;
    }

    /**
     * Analyze a referrer URL to extract search engine information and query
     *
     * @param string $referer The HTTP referer URL
     * @return array|null Array with 'engine', 'name', 'query' keys or null if not a search engine
     */
    protected function analyzeReferrer(string $referer): ?array
    {
        $urlparts = parse_url(strtolower($referer));
        if (!isset($urlparts['host'])) {
            return null;
        }
        
        $domain = $urlparts['host'];
        $queryString = $urlparts['query'] ?? $urlparts['fragment'] ?? '';
        
        if (!$queryString) {
            return null;
        }

        parse_str($queryString, $params);

        // Try to match against known search engines
        $result = $this->matchKnownEngine($domain, $params);
        if ($result) {
            return $result;
        }

        // Try generic search parameters
        return $this->matchGenericEngine($domain, $params);
    }

    /**
     * Try to match against known search engines
     *
     * @param string $domain The domain to check
     * @param array $params URL parameters
     * @return array|null Match result or null
     */
    protected function matchKnownEngine(string $domain, array $params): ?array
    {
        foreach ($this->searchEngines as $key => $engine) {
            if (!$engine['regex']) {
                continue; // skip engines without regex (like dokuwiki)
            }
            
            if (preg_match('/' . $engine['regex'] . '/', $domain)) {
                $query = $this->extractQuery($params, $engine['params']);
                if ($query) {
                    return [
                        'engine' => $key,
                        'name' => $engine['name'],
                        'query' => $query
                    ];
                }
            }
        }
        
        return null;
    }

    /**
     * Try to match against generic search parameters
     *
     * @param string $domain The domain to check
     * @param array $params URL parameters
     * @return array|null Match result or null
     */
    protected function matchGenericEngine(string $domain, array $params): ?array
    {
        $genericParams = ['search', 'query', 'q', 'keywords', 'keyword'];
        $query = $this->extractQuery($params, $genericParams);
        
        if (!$query) {
            return null;
        }

        // Generate engine name from domain
        $engineName = preg_replace('/(\.co)?\.([a-z]{2,5})$/', '', $domain);
        $engineName = array_pop(explode('.', $engineName));
        
        return [
            'engine' => 'generic_' . $engineName,
            'name' => ucfirst($engineName),
            'query' => $query
        ];
    }

    /**
     * Extract and clean search query from parameters
     *
     * @param array $params URL parameters
     * @param array $paramNames Parameter names to check
     * @return string|null Cleaned query or null
     */
    protected function extractQuery(array $params, array $paramNames): ?string
    {
        foreach ($paramNames as $param) {
            if (!empty($params[$param])) {
                $query = $this->cleanQuery($params[$param]);
                if ($query) {
                    return $query;
                }
            }
        }
        
        return null;
    }

    /**
     * Clean and validate search query
     *
     * @param string $query Raw query string
     * @return string|null Cleaned query or null if invalid
     */
    protected function cleanQuery(string $query): ?string
    {
        // Remove non-search queries
        $query = preg_replace('/^(cache|related):[^\+]+/', '', $query);
        // Compact whitespace
        $query = preg_replace('/ +/', ' ', $query);
        $query = trim($query);
        
        return $query ?: null;
    }

}
