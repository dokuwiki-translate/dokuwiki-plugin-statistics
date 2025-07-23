<?php

namespace dokuwiki\plugin\statistics;

/**
 * Extract search Engine Inormation from the HTTP referer
 *
 * We use the HTTP specification misspelling of "referer" here
 */
class SearchEngines
{
    /** @var array Search engine definitions with regex patterns and metadata */
    protected static array $searchEngines = [
        'dokuwiki' => [
            'name' => 'DokuWiki Internal Search',
            'url' => DOKU_URL,
            'regex' => '', // set in constructor
            'params' => ['q']
        ],
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

    /** @var string|null The search engine key */
    protected ?string $engine = null;

    /** @var string|null The search engine name */
    protected ?string $name = null;

    /** @var string|null The search query */
    protected ?string $query = null;

    /**
     * Constructor
     *
     * @param string $referer The HTTP referer URL to analyze
     */
    public function __construct(string $referer)
    {
        // Add regex matching ourselves
        self::$searchEngines['dokuwiki']['regex'] = '^' . preg_quote(parse_url(DOKU_URL, PHP_URL_HOST), '/') . '$';
        $this->analyze($referer);
    }

    /**
     * Check if the referer is from a search engine
     *
     * @return bool True if the referer is from a search engine
     */
    public function isSearchEngine(): bool
    {
        return (bool)$this->engine;
    }

    /**
     * Get the search engine identifier from the referer
     *
     * @return string|null The search engine or null if not a search engine
     */
    public function getEngine(): ?string
    {
        return $this->engine;
    }

    /**
     * Get the search query from the referer
     *
     * @return string|null The search query or null if not a search engine
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * Get the search engine name for the given engine identifier
     *
     * @return string If we have a name for the engine, return it, otherwise return capitalized $engine
     */
    public static function getName($engine): string
    {
        return isset(self::$searchEngines[$engine]) ? self::$searchEngines[$engine]['name'] : ucfirst($engine);
    }

    /**
     * Get the search engine URL for the given engine identifier
     *
     * @return string|null The search engine URL or null if not defined
     */
    public static function getUrl($engine): ?string
    {
        return isset(self::$searchEngines[$engine]) ? self::$searchEngines[$engine]['url'] : null;
    }

    /**
     * Analyze the referer and populate member variables
     */
    protected function analyze(string $referer): void
    {
        $result = $this->analyzereferer($referer);

        if ($result) {
            $this->engine = $result['engine'];
            $this->name = $result['name'];
            $this->query = $result['query'];
        }
    }

    /**
     * Analyze a referer URL to extract search engine information and query
     *
     * @param string $referer The HTTP referer URL
     * @return array|null Array with 'engine', 'name', 'query' keys or null if not a search engine
     */
    protected function analyzereferer(string $referer): ?array
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
        foreach (self::$searchEngines as $key => $engine) {
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
        $domainParts = explode('.', $engineName);
        $engineName = array_pop($domainParts);

        return [
            'engine' => $engineName,
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
        // Remove non-search queries (cache: and related: prefixes)
        $query = preg_replace('/^(cache|related):[^\s]+\s*/', '', $query);
        // Compact whitespace
        $query = preg_replace('/\s+/', ' ', $query);
        $query = trim($query);

        return $query ?: null;
    }

}
