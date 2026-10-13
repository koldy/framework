<?php

declare(strict_types=1);

namespace Tests\PhpUnit;

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

    public function testIsTrustedProxyIgnoresInvalidPrefixLength(): void
    {
        // '/abc' used to be cast to /0 (trust everything), '/33' used to throw ArithmeticError
        Request::setTrustedProxies(['10.0.0.0/33', '10.0.0.0/abc', '10.0.0.0/', '10.0.0.0/-1']);
        $this->assertFalse(TestableRequest::callIsTrustedProxy('10.0.0.1'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('1.2.3.4'));
    }

    public function testIsTrustedProxyMatchesIpInCidrWithPartialByte(): void
    {
        // /20 splits the third octet: 172.16.0.0 – 172.16.15.255
        Request::setTrustedProxies(['172.16.0.0/20']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('172.16.15.255'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('172.16.16.0'));
    }

    public function testIsTrustedProxyMatchesIpv6Cidr(): void
    {
        Request::setTrustedProxies(['2001:db8::/32']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('2001:db8::1'));
        $this->assertTrue(TestableRequest::callIsTrustedProxy('2001:db8:ffff:ffff:ffff:ffff:ffff:ffff'));
    }

    public function testIsTrustedProxyRejectsIpOutsideIpv6Cidr(): void
    {
        Request::setTrustedProxies(['2001:db8::/32']);
        $this->assertFalse(TestableRequest::callIsTrustedProxy('2001:db9::1'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('2a00:1450::1'));
    }

    public function testIsTrustedProxyMatchesIpv6CidrWithPartialByte(): void
    {
        // fc00::/7 = unique local addresses, fc00:: – fdff:ffff:...
        Request::setTrustedProxies(['fc00::/7']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('fd12:3456::1'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('fe80::1'));
    }

    public function testIsTrustedProxyMatchesIpv6CidrSlash128(): void
    {
        Request::setTrustedProxies(['2001:db8::1/128']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('2001:db8::1'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('2001:db8::2'));
    }

    public function testIsTrustedProxyMatchesIpv6CidrSlash0(): void
    {
        Request::setTrustedProxies(['::/0']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('::1'));
        $this->assertTrue(TestableRequest::callIsTrustedProxy('2a00:1450::1'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('1.2.3.4'));
    }

    public function testIsTrustedProxyDoesNotMixAddressFamilies(): void
    {
        Request::setTrustedProxies(['0.0.0.0/0']);
        $this->assertFalse(TestableRequest::callIsTrustedProxy('::1'));
    }

    public function testIsTrustedProxyMatchesExactIpv6InNormalisedForm(): void
    {
        Request::setTrustedProxies(['0:0:0:0:0:0:0:1', '2001:DB8::A']);
        $this->assertTrue(TestableRequest::callIsTrustedProxy('::1'));
        $this->assertTrue(TestableRequest::callIsTrustedProxy('2001:db8:0:0:0:0:0:a'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy('::2'));
    }

    public function testIsTrustedProxyRejectsInvalidIp(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8', 'garbage']);
        $this->assertFalse(TestableRequest::callIsTrustedProxy('garbage'));
        $this->assertFalse(TestableRequest::callIsTrustedProxy(''));
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

    public function testIpWithCommaSeparatedXForwardedForPicksRightmostUntrustedIp(): void
    {
        // Proxies append to X-Forwarded-For, so only the right end is trustworthy;
        // the right-most entry not on the allowlist is the real client
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '9.8.7.6, 2.3.4.5']);
        $this->assertSame('2.3.4.5', Request::ip());
    }

    public function testIpWithNoTrustedProxiesKeepsLegacyLeftmostPublicIp(): void
    {
        // Backward compat: without an allowlist the first public IP still wins
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '9.8.7.6, 2.3.4.5']);
        $this->assertSame('9.8.7.6', Request::ip());
    }

    public function testIpIgnoresSpoofedLeftmostXForwardedForEntry(): void
    {
        // Client sent "X-Forwarded-For: 6.6.6.6", the proxy appended the real address
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 1.2.3.4']);
        $this->assertSame('1.2.3.4', Request::ip());
    }

    public function testIpSkipsEveryTrustedProxyLayer(): void
    {
        Request::setTrustedProxies(['10.0.0.1', '10.0.0.2']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 10.0.0.2']);
        $this->assertSame('1.2.3.4', Request::ip());
    }

    public function testIpStopsAtFirstUnlistedProxyLayer(): void
    {
        // Only the proxy PHP connects to is listed; the next layer is treated as the client
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 10.0.0.2']);
        $this->assertSame('10.0.0.2', Request::ip());
    }

    public function testIpSkipsCidrTrustedProxyLayers(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 1.2.3.4, 10.1.1.1']);
        $this->assertSame('1.2.3.4', Request::ip());
    }

    public function testIpReturnsPrivateClientBehindTrustedProxy(): void
    {
        // VPN / intranet clients have private addresses and must not fall back to the proxy
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '192.168.1.50']);
        $this->assertSame('192.168.1.50', Request::ip());
    }

    public function testIpReturnsLeftmostEntryWhenEveryHopIsTrusted(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '10.0.0.3']);
        $this->assertSame('10.0.0.3', Request::ip());
    }

    public function testIpIgnoresMalformedEntryLeftOfClient(): void
    {
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'not-an-ip, 1.2.3.4']);
        $this->assertSame('1.2.3.4', Request::ip());
    }

    public function testIpStopsAtMalformedEntryAndReturnsRemoteAddr(): void
    {
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, garbage']);
        $this->assertSame('10.0.0.1', Request::ip());
    }

    public function testIpStopsAtMalformedEntryAndReturnsHopToItsRight(): void
    {
        // The walk never crosses a malformed entry, even when everything right of it is trusted
        Request::setTrustedProxies(['10.0.0.0/8']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, garbage, 10.0.0.2']);
        $this->assertSame('10.0.0.2', Request::ip());
    }

    public function testIpTreatsEmptyXForwardedForSegmentAsMalformed(): void
    {
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4,']);
        $this->assertSame('10.0.0.1', Request::ip());
    }

    public function testIpIgnoresClientIpHeaderWhenAllowlistIsSet(): void
    {
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_CLIENT_IP' => '6.6.6.6']);
        $this->assertSame('10.0.0.1', Request::ip());
    }

    public function testIpIgnoresOtherProxyHeadersWhenAllowlistIsSet(): void
    {
        Request::setTrustedProxies(['10.0.0.1']);
        Mock::server([
            'REMOTE_ADDR'              => '10.0.0.1',
            'HTTP_CLIENT_IP'           => '6.6.6.6',
            'HTTP_X_FORWARDED'         => '6.6.6.7',
            'HTTP_X_CLUSTER_CLIENT_IP' => '6.6.6.8',
            'HTTP_FORWARDED_FOR'       => '6.6.6.9',
            'HTTP_FORWARDED'           => 'for=6.6.6.10',
            'HTTP_X_FORWARDED_FOR'     => '1.2.3.4',
        ]);
        $this->assertSame('1.2.3.4', Request::ip());
    }

    public function testIpWithIpv6TrustedProxyCidr(): void
    {
        Request::setTrustedProxies(['2001:db8::/32']);
        Mock::server(['REMOTE_ADDR' => '2001:db8::1', 'HTTP_X_FORWARDED_FOR' => '2a00:1450::1']);
        $this->assertSame('2a00:1450::1', Request::ip());
    }

    public function testIpWithEmptyAllowlistReturnsRemoteAddr(): void
    {
        Request::setTrustedProxies([]);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('10.0.0.1', Request::ip());
    }

    public function testIpWithAllowlistFromApplicationConfigWalksFromTheRight(): void
    {
        Application::getApplicationConfig()->set('trusted_proxies', ['10.0.0.0/8']);
        Mock::server(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 1.2.3.4, 10.1.1.1']);
        $this->assertSame('1.2.3.4', Request::ip());
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
