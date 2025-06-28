<?php

namespace dokuwiki\plugin\statistics\test;

use DokuWikiTest;
use dokuwiki\plugin\statistics\Logger;
use helper_plugin_statistics;

/**
 * Tests for the statistics plugin Logger class
 *
 * @group plugin_statistics
 * @group plugins
 */
class LoggerTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['statistics', 'sqlite'];

    /** @var helper_plugin_statistics */
    protected $helper;

    /** @var Logger */
    protected $logger;

    public function setUp(): void
    {
        parent::setUp();

        // Load the helper plugin
        $this->helper = plugin_load('helper', 'statistics');

        // Mock user agent to avoid bot detection
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

        // Initialize logger
        $this->logger = new Logger($this->helper);
    }

    public function tearDown(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    /**
     * Test constructor initializes properties correctly
     */
    public function testConstructor()
    {
        $this->assertInstanceOf(Logger::class, $this->logger);

        // Test that bot user agents throw exception
        $_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1 (+http://www.google.com/bot.html)';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Bot detected, not logging');
        new Logger($this->helper);
    }

    /**
     * Test begin and end transaction methods
     */
    public function testBeginEnd()
    {
        $this->logger->begin();

        // Verify transaction is active by checking PDO
        $pdo = $this->helper->getDB()->getPdo();
        $this->assertTrue($pdo->inTransaction());

        $this->logger->end();

        // Verify transaction is committed
        $this->assertFalse($pdo->inTransaction());
    }

    /**
     * Test logLastseen method
     */
    public function testLogLastseen()
    {
        global $INPUT;

        // Test with no user (should not log)
        $INPUT->server->set('REMOTE_USER', '');
        $this->logger->logLastseen();

        $count = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM lastseen');
        $this->assertEquals(0, $count);

        // Test with user
        $INPUT->server->set('REMOTE_USER', 'testuser');
        $this->logger->logLastseen();

        $count = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM lastseen');
        $this->assertEquals(1, $count);

        $user = $this->helper->getDB()->queryValue('SELECT user FROM lastseen WHERE user = ?', ['testuser']);
        $this->assertEquals('testuser', $user);
    }

    /**
     * Data provider for logGroups test
     */
    public function logGroupsProvider()
    {
        return [
            'empty groups' => [[], 'view', 0],
            'single group' => [['admin'], 'view', 1],
            'multiple groups' => [['admin', 'user'], 'edit', 2],
            'filtered groups' => [['admin', 'nonexistent'], 'view', 1], // assuming only 'admin' is configured
        ];
    }

    /**
     * Test logGroups method
     * @dataProvider logGroupsProvider
     */
    public function testLogGroups($groups, $type, $expectedCount)
    {
        global $conf;
        $conf['plugin']['statistics']['loggroups'] = ['admin', 'user'];

        $this->logger->logGroups($type, $groups);

        $count = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM groups WHERE type = ?', [$type]);
        $this->assertEquals($expectedCount, $count);

        if ($expectedCount > 0) {
            $loggedGroups = $this->helper->getDB()->queryAll('SELECT `group` FROM groups WHERE type = ?', [$type]);
            $this->assertCount($expectedCount, $loggedGroups);
        }
    }

    /**
     * Data provider for logExternalSearch test
     */
    public function logExternalSearchProvider()
    {
        return [
            'google search' => [
                'https://www.google.com/search?q=dokuwiki+test',
                'search',
                'dokuwiki test',
                'google'
            ],
            'non-search referer' => [
                'https://example.com/page',
                '',
                null,
                null
            ],
        ];
    }

    /**
     * Test logExternalSearch method
     * @dataProvider logExternalSearchProvider
     */
    public function testLogExternalSearch($referer, $expectedType, $expectedQuery, $expectedEngine)
    {
        global $INPUT;
        $INPUT->set('p', 'test:page');

        $type = '';
        $this->logger->logExternalSearch($referer, $type);

        $this->assertEquals($expectedType, $type);

        if ($expectedType === 'search') {
            $searchCount = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM search');
            $this->assertEquals(1, $searchCount);

            $search = $this->helper->getDB()->queryRecord('SELECT * FROM search ORDER BY dt DESC LIMIT 1');
            $this->assertEquals($expectedQuery, $search['query']);
            $this->assertEquals($expectedEngine, $search['engine']);
        }
    }

    /**
     * Test logSearch method
     */
    public function testLogSearch()
    {
        $page = 'test:page';
        $query = 'test search query';
        $words = ['test', 'search', 'query'];
        $engine = 'Google';

        $this->logger->logSearch($page, $query, $words, $engine);

        // Check search table
        $search = $this->helper->getDB()->queryRecord('SELECT * FROM search ORDER BY dt DESC LIMIT 1');
        $this->assertEquals($page, $search['page']);
        $this->assertEquals($query, $search['query']);
        $this->assertEquals($engine, $search['engine']);

        // Check searchwords table
        $wordCount = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM searchwords WHERE sid = ?', [$search['id']]);
        $this->assertEquals(3, $wordCount);

        $loggedWords = $this->helper->getDB()->queryAll('SELECT word FROM searchwords WHERE sid = ? ORDER BY word', [$search['id']]);
        $this->assertEquals(['query', 'search', 'test'], array_column($loggedWords, 'word'));
    }

    /**
     * Test logSession method
     */
    public function testLogSession()
    {
        // Test without adding view
        $this->logger->logSession(0);

        $sessionCount = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM session');
        $this->assertEquals(1, $sessionCount);

        $session = $this->helper->getDB()->queryRecord('SELECT * FROM session ORDER BY dt DESC LIMIT 1');
        $this->assertEquals(0, $session['views']);

        // Test adding view
        $this->logger->logSession(1);

        $session = $this->helper->getDB()->queryRecord('SELECT * FROM session ORDER BY dt DESC LIMIT 1');
        $this->assertEquals(1, $session['views']);

        // Test incrementing views
        $this->logger->logSession(1);

        $session = $this->helper->getDB()->queryRecord('SELECT * FROM session ORDER BY dt DESC LIMIT 1');
        $this->assertEquals(2, $session['views']);
    }

    /**
     * Test logIp method
     */
    public function testLogIp()
    {
        $ip = '8.8.8.8';

        // Mock HTTP client response
        $this->markTestSkipped('Requires mocking HTTP client for external API call');

        // This test would need to mock the DokuHTTPClient to avoid actual API calls
        // For now, we'll skip it as the requirement was not to mock anything
    }

    /**
     * Test logOutgoing method
     */
    public function testLogOutgoing()
    {
        global $INPUT;

        // Test without outgoing link
        $INPUT->set('ol', '');
        $this->logger->logOutgoing();

        $count = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM outlinks');
        $this->assertEquals(0, $count);

        // Test with outgoing link
        $link = 'https://example.com';
        $page = 'test:page';
        $INPUT->set('ol', $link);
        $INPUT->set('p', $page);

        $this->logger->logOutgoing();

        $count = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM outlinks');
        $this->assertEquals(1, $count);

        $outlink = $this->helper->getDB()->queryRecord('SELECT * FROM outlinks ORDER BY dt DESC LIMIT 1');
        $this->assertEquals($link, $outlink['link']);
        $this->assertEquals(md5($link), $outlink['link_md5']);
        $this->assertEquals($page, $outlink['page']);
    }

    /**
     * Test logAccess method
     */
    public function testLogAccess()
    {
        global $INPUT, $USERINFO, $conf;

        $conf['plugin']['statistics']['loggroups'] = ['admin', 'user'];

        $page = 'test:page';
        $referer = 'https://example.com';
        $user = 'testuser';

        $INPUT->set('p', $page);
        $INPUT->set('r', $referer);
        $INPUT->set('sx', 1920);
        $INPUT->set('sy', 1080);
        $INPUT->set('vx', 1200);
        $INPUT->set('vy', 800);
        $INPUT->set('js', 1);
        $INPUT->server->set('REMOTE_USER', $user);

        $USERINFO = ['grps' => ['admin', 'user']];

        $this->logger->logAccess();

        // Check access table
        $access = $this->helper->getDB()->queryRecord('SELECT * FROM access ORDER BY dt DESC LIMIT 1');
        $this->assertEquals($page, $access['page']);
        $this->assertEquals($user, $access['user']);
        $this->assertEquals(1920, $access['screen_x']);
        $this->assertEquals(1080, $access['screen_y']);
        $this->assertEquals(1200, $access['view_x']);
        $this->assertEquals(800, $access['view_y']);
        $this->assertEquals(1, $access['js']);
        $this->assertEquals('external', $access['ref_type']);

        // Check refseen table
        $refCount = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM refseen WHERE ref_md5 = ?', [md5($referer)]);
        $this->assertEquals(1, $refCount);

        // Check groups table
        $groupCount = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM groups WHERE type = ?', ['view']);
        $this->assertEquals(2, $groupCount);
    }

    /**
     * Data provider for logMedia test
     */
    public function logMediaProvider()
    {
        return [
            'image inline' => ['test.jpg', 'image/jpeg', true, 1024],
            'video not inline' => ['test.mp4', 'video/mp4', false, 2048],
            'document' => ['test.pdf', 'application/pdf', false, 512],
        ];
    }

    /**
     * Test logMedia method
     * @dataProvider logMediaProvider
     */
    public function testLogMedia($media, $mime, $inline, $size)
    {
        global $INPUT;

        $user = 'testuser';
        $INPUT->server->set('REMOTE_USER', $user);

        $this->logger->logMedia($media, $mime, $inline, $size);

        $mediaLog = $this->helper->getDB()->queryRecord('SELECT * FROM media ORDER BY dt DESC LIMIT 1');
        $this->assertEquals($media, $mediaLog['media']);
        $this->assertEquals($user, $mediaLog['user']);
        $this->assertEquals($size, $mediaLog['size']);
        $this->assertEquals($inline ? 1 : 0, $mediaLog['inline']);

        [$mime1, $mime2] = explode('/', strtolower($mime));
        $this->assertEquals($mime1, $mediaLog['mime1']);
        $this->assertEquals($mime2, $mediaLog['mime2']);
    }

    /**
     * Data provider for logEdit test
     */
    public function logEditProvider()
    {
        return [
            'create page' => ['new:page', 'create'],
            'edit page' => ['existing:page', 'edit'],
            'delete page' => ['old:page', 'delete'],
        ];
    }

    /**
     * Test logEdit method
     * @dataProvider logEditProvider
     */
    public function testLogEdit($page, $type)
    {
        global $INPUT, $USERINFO, $conf;

        $conf['plugin']['statistics']['loggroups'] = ['admin'];

        $user = 'testuser';
        $INPUT->server->set('REMOTE_USER', $user);
        $USERINFO = ['grps' => ['admin']];

        $this->logger->logEdit($page, $type);

        // Check edits table
        $edit = $this->helper->getDB()->queryRecord('SELECT * FROM edits ORDER BY dt DESC LIMIT 1');
        $this->assertEquals($page, $edit['page']);
        $this->assertEquals($type, $edit['type']);
        $this->assertEquals($user, $edit['user']);

        // Check groups table
        $groupCount = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM groups WHERE type = ?', ['edit']);
        $this->assertEquals(1, $groupCount);
    }

    /**
     * Data provider for logLogin test
     */
    public function logLoginProvider()
    {
        return [
            'login' => ['login', 'testuser'],
            'logout' => ['logout', 'testuser'],
            'create' => ['create', 'newuser'],
        ];
    }

    /**
     * Test logLogin method
     * @dataProvider logLoginProvider
     */
    public function testLogLogin($type, $user)
    {
        global $INPUT;

        if ($user === 'testuser') {
            $INPUT->server->set('REMOTE_USER', $user);
            $this->logger->logLogin($type);
        } else {
            $this->logger->logLogin($type, $user);
        }

        $login = $this->helper->getDB()->queryRecord('SELECT * FROM logins ORDER BY dt DESC LIMIT 1');
        $this->assertEquals($type, $login['type']);
        $this->assertEquals($user, $login['user']);
    }

    /**
     * Test logHistoryPages method
     */
    public function testLogHistoryPages()
    {
        $this->logger->logHistoryPages();

        // Check that both page_count and page_size entries were created
        $pageCount = $this->helper->getDB()->queryValue('SELECT value FROM history WHERE info = ?', ['page_count']);
        $pageSize = $this->helper->getDB()->queryValue('SELECT value FROM history WHERE info = ?', ['page_size']);

        $this->assertIsNumeric($pageCount);
        $this->assertIsNumeric($pageSize);
        $this->assertGreaterThanOrEqual(0, $pageCount);
        $this->assertGreaterThanOrEqual(0, $pageSize);
    }

    /**
     * Test logHistoryMedia method
     */
    public function testLogHistoryMedia()
    {
        $this->logger->logHistoryMedia();

        // Check that both media_count and media_size entries were created
        $mediaCount = $this->helper->getDB()->queryValue('SELECT value FROM history WHERE info = ?', ['media_count']);
        $mediaSize = $this->helper->getDB()->queryValue('SELECT value FROM history WHERE info = ?', ['media_size']);

        $this->assertIsNumeric($mediaCount);
        $this->assertIsNumeric($mediaSize);
        $this->assertGreaterThanOrEqual(0, $mediaCount);
        $this->assertGreaterThanOrEqual(0, $mediaSize);
    }

    /**
     * Test that feedreader user agents are handled correctly
     */
    public function testFeedReaderUserAgent()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'FeedBurner/1.0 (http://www.FeedBurner.com)';

        $logger = new Logger($this->helper);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($logger);
        $uaTypeProperty = $reflection->getProperty('uaType');
        $uaTypeProperty->setAccessible(true);

        $this->assertEquals('feedreader', $uaTypeProperty->getValue($logger));
    }

    /**
     * Test session logging only works for browser type
     */
    public function testLogSessionOnlyForBrowser()
    {
        // Change user agent type to feedreader using reflection
        $reflection = new \ReflectionClass($this->logger);
        $uaTypeProperty = $reflection->getProperty('uaType');
        $uaTypeProperty->setAccessible(true);
        $uaTypeProperty->setValue($this->logger, 'feedreader');

        $this->logger->logSession(1);

        $sessionCount = $this->helper->getDB()->queryValue('SELECT COUNT(*) FROM session');
        $this->assertEquals(0, $sessionCount);
    }
}
