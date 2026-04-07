<?php

declare(strict_types=1);

namespace Tests;

use Koldy\Application;
use Koldy\Mock;
use Koldy\Request;
use PHPUnit\Framework\TestCase;

/**
 * Exposes Request::isTrustedProxy() (protected) so it can be unit-tested directly.
 */
class TestableRequest extends Request
{

    public static function callIsTrustedProxy(string $ip): bool
    {
        return static::isTrustedProxy($ip);
    }

}

class RequestTrustedProxyTest extends TestCase
{

    private static bool $appInitialized = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$appInitialized) {
            $_SERVER['SCRIPT_FILENAME'] = __FILE__;

            Application::useConfig([
                'site_url' => 'http://localhost',
                'env'      => Application::TEST,
                'key'      => 'RequestProxyTestKey1234',
                'timezone' => 'UTC',
                'paths'    => [
                    'application' => __DIR__ . '/',
                    'storage'     => __DIR__ . '/',
                ],
            ]);

            self::$appInitialized = true;
        }
    }

    protected function setUp(): void
    {
        Mock::start();
    }

    protected function tearDown(): void
    {
        try {
            Application::getApplicationConfig()->delete('trusted_proxies');
        } catch (\Throwable) {}

        Mock::reset();
    }

    // ── setTrustedProxies / getTrustedProxies ─────────────────────────────────

    public function testGetTrustedProxiesReturnsNullByDefault(): void
    {
        $this->assertNull(Request::getTrustedProxies());
    }

    public function testSetAndGetTrustedProxies(): void
    {
        Request::setTrustedProxies(['10.0.0.1', '10.0.0.2']);
        $this->assertSame(['10.0.0.1', '10.0.0.2'], Request::getTrustedProxies());
    }

    public function testSetTrustedProxiesWithEmptyArray(): void
    {
        Request::setTrustedProxies([]);
        $this->assertSame([], Request::getTrustedProxies());
    }

    public function testSetTrustedProxiesSkipsConfigLazyLoad(): void
    {
        // Even if the app config has a different list, an explicit setTrustedProxies() must win
        Application::getApplicationConfig()->set('trusted_proxies', ['9.9.9.9']);
        Request::setTrustedProxies(['1.1.1.1']);
        $this->assertSame(['1.1.1.1'], Request::getTrustedProxies());
    }

    // ── lazy loading from application config ──────────────────────────────────

    public function testGetTrustedProxiesLazyLoadsFromApplicationConfig(): void
    {
        Application::getApplicationConfig()->set('trusted_proxies', ['10.1.2.3', '10.1.2.4']);
        $this->assertSame(['10.1.2.3', '10.1.2.4'], Request::getTrustedProxies());
    }

    public function testGetTrustedProxiesReturnsNullWhenConfigKeyAbsent(): void
    {
        // No trusted_proxies key — must return null without throwing
        $this->assertNull(Request::getTrustedProxies());
    }

    public function testLazyLoadResultIsCachedOnSubsequentCalls(): void
    {
        Application::getApplicationConfig()->set('trusted_proxies', ['10.0.0.1']);
        $first = Request::getTrustedProxies();
        // Remove the key — second call must return the cached list, not null
        Application::getApplicationConfig()->delete('trusted_proxies');
        $second = Request::getTrustedProxies();
        $this->assertSame(['10.0.0.1'], $first);
        $this->assertSame($first, $second);
    }

    // ── isTrustedProxy ────────────────────────────────────────────────────────

    public function testIsTrustedProxyReturnsTrueWhenNoProxiesConfigured(): void
    {
        // null list → trust all (backwards-compatible default)
        $this->assertTrue(TestableRequest::callIsTrustedProxy('1.2.3.4'));
        $this->assertTrue(TestableRequest::callIsTrustedProxy('10.0.0.1'));
    }

    public function testIsTrustedProxyReturnsFalseForEmptyList(): void
    {
        Request::setTrustedProxies([]);
        $this->assertFalse(TestableRequest::callIsTrustedProxy('1.2.3.4'));
    }

    public function testIsTrustedProxyMatchesExactIp(): void
    {
        Request::setTrustedProxies(['10.0.0.1', '10.0.0.2']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('10.0.0.1'));
        $this->assertTrue(TestableRequest::callIsTrustedProxy('10.0.0.2'));
    }

    public function testIsTrustedProxyRejectsIpNotInList(): void
    {
        Request::setTrustedProxies(['10.0.0.1']);
        $this->assertFalse(TestableRequest::callIsTrustedProxy('10.0.0.3'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('1.2.3.4'));
    }

    public function testIsTrustedProxyMatchesIpInCidr24(): void
    {
        Request::setTrustedProxies(['10.0.0.0/24']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('10.0.0.1'));
        $this->assertTrue(TestableRequest::callIsTrustedProxy('10.0.0.254'));
    }

    public function testIsTrustedProxyRejectsIpOutsideCidr24(): void
    {
        Request::setTrustedProxies(['10.0.0.0/24']);
        $this->assertFalse(TestableRequest::callIsTrustedProxy('10.0.1.0'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('192.168.0.1'));
    }

    public function testIsTrustedProxyMatchesIpInCidr8(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('10.1.2.3'));
        $this->assertTrue(TestableRequest::callIsTrustedProxy('10.255.255.255'));
    }

    public function testIsTrustedProxyRejectsIpOutsideCidr8(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8']);
        $this->assertFalse(TestableRequest::callIsTrustedProxy('11.0.0.1'));
    }

    public function testIsTrustedProxyMatchesCidrSlash32(): void
    {
        // /32 = single-host; neighbour must not match
        Request::setTrustedProxies(['192.168.1.50/32']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('192.168.1.50'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('192.168.1.51'));
    }

    public function testIsTrustedProxyMatchesCidrSlash0(): void
    {
        // /0 = every IPv4 address
        Request::setTrustedProxies(['0.0.0.0/0']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('1.2.3.4'));
        $this->assertTrue(TestableRequest::callIsTrustedProxy('255.255.255.255'));
    }

    public function testIsTrustedProxyIgnoresMalformedCidrEntry(): void
    {
        // A bad entry must be silently skipped; subsequent valid entries still work
        Request::setTrustedProxies(['not-an-ip/24', '10.0.0.1']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('10.0.0.1'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('10.0.0.2'));
    }

    // ── ip() integration ──────────────────────────────────────────────────────

    public function testIpWithNoTrustedProxiesHonorsXForwardedFor(): void
    {
        // Backward compat: no allowlist → all proxy headers trusted
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('1.2.3.4', Request::ip());
    }

    public function testIpWithNoTrustedProxiesHonorsHttpClientIp(): void
    {
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_CLIENT_IP' => '5.6.7.8']);
        $this->assertSame('5.6.7.8', Request::ip());
    }

    public function testIpWithTrustedProxyRemoteAddrInListUsesForwardedHeader(): void
    {
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('1.2.3.4', Request::ip());
    }

    public function testIpWithTrustedProxyRemoteAddrNotInListIgnoresForwardedHeader(): void
    {
        Request::setTrustedProxies(['10.0.0.2']); // 10.0.0.1 is NOT trusted
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('10.0.0.1', Request::ip());
    }

    public function testIpSpoofedHeaderFromUntrustedOriginIsIgnored(): void
    {
        Request::setTrustedProxies(['10.0.0.1']);
        // Attacker is directly connected (REMOTE_ADDR = 5.5.5.5, not trusted) and
        // crafts HTTP_X_FORWARDED_FOR to impersonate a different IP
        Mock::server(['REMOTE_ADDR' => '5.5.5.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('5.5.5.5', Request::ip());
    }

    public function testIpWithTrustedProxyCidrInRangeUsesForwardedHeader(): void
    {
        Request::setTrustedProxies(['10.0.0.0/24']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('1.2.3.4', Request::ip());
    }

    public function testIpWithTrustedProxyCidrOutsideRangeUsesRemoteAddr(): void
    {
        Request::setTrustedProxies(['10.0.0.0/24']);
        Mock::server(['REMOTE_ADDR' => '10.0.1.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('10.0.1.1', Request::ip());
    }

    public function testIpWithCommaSeparatedXForwardedForPicksFirstPublicIp(): void
    {
        // X-Forwarded-For chains look like "client, proxy1, proxy2"; first public IP wins
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '9.8.7.6, 2.3.4.5']);
        $this->assertSame('9.8.7.6', Request::ip());
    }

    public function testSetTrustedProxiesAfterIpCachedResetsAndReEvaluates(): void
    {
        // First call — no allowlist, proxy header trusted, result cached
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('1.2.3.4', Request::ip());

        // Lock down proxies — setTrustedProxies() must clear the IP cache
        Request::setTrustedProxies(['10.0.0.2']); // 10.0.0.1 no longer trusted
        $this->assertSame('10.0.0.1', Request::ip());
    }

}
