<?php

/**
 * Defines regular expressions for the most common search engines
 */

$SEARCHENGINEINFO = [
    'dokuwiki'   => ['DokuWiki Internal Search', wl()],
    'google'     => ['Google', 'http://www.google.com'],
    'yahoo'      => ['Yahoo!', 'http://www.yahoo.com'],
    'yandex'     => ['Яндекс (Yandex)', 'http://www.yandex.ru'],
    'naver'      => ['네이버 (Naver)', 'http://www.naver.com'],
    'baidu'      => ['百度 (Baidu)', 'http://www.baidu.com'],
    'ask'        => ['Ask', 'http://www.ask.com'],
    'babylon'    => ['Babylon', 'http://search.babylon.com'],
    'aol'        => ['AOL Search', 'http://search.aol.com'],
    'duckduckgo' => ['DuckDuckGo', 'http://duckduckgo.com'],
    'bing'       => ['Bing', 'http://www.bing.com']
];

$SEARCHENGINES = [
    '^(\w+\.)*google(\.co)?\.([a-z]{2,5})$' => ['google', 'q'],
    '^(\w+\.)*bing(\.co)?\.([a-z]{2,5})$'   => ['bing', 'q'],
    '^(\w+\.)*yandex(\.co)?\.([a-z]{2,5})$' => ['yandex', 'query'],
    '^(\w+\.)*yahoo\.com$'                  => ['yahoo', 'p'],
    '^search\.naver\.com$'                  => ['naver', 'query'],
    '^(\w+\.)*baidu\.com$'                  => ['baidu', 'wd', 'word', 'kw'],
    '^search\.avg\.com$'                    => ['google', 'q'],
    '^(\w+\.)*ask\.com$'                    => ['ask', 'ask', 'q', 'searchfor'],
    '^(\w+\.)*search-results\.com$'         => ['ask', 'ask', 'q', 'searchfor'],
    '^search\.babylon\.com$'                => ['babylon', 'q'],
    '^(\w+\.)*(aol)?((search|recherches?|images|suche|alicesuche)\.)aol(\.co)?\.([a-z]{2,5})$' => ['aol', 'query', 'q'],
    '^duckduckgo\.com$'                     => ['duckduckgo', 'q']
];
