<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest\Helpers;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Rest\Helpers\RootServerValidator;

class RootServerValidatorTest extends TestCase {

    /**
     * A valid, reachable BMLT root server returns the normalized URL.
     */
    public function testValidReachableRootServerReturnsNormalizedUrl(): void {
        $this->mockWpRemoteGet([
            'GetServerInfo' => [
                'code' => 200,
                'body' => [['version' => '3.0.0', 'versionInt' => '3000000']],
            ],
        ]);

        // Trailing slash should be stripped during normalization.
        $result = RootServerValidator::validate('https://bmlt.example.com/main_server/');

        $this->assertSame('https://bmlt.example.com/main_server', $result);
    }

    /**
     * An empty value is rejected with a clear error.
     */
    public function testEmptyUrlIsRejected(): void {
        $result = RootServerValidator::validate('');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_root_server_url', $result->get_error_code());
    }

    /**
     * A malformed / non-URL string is rejected without a network call.
     */
    public function testMalformedUrlIsRejected(): void {
        $result = RootServerValidator::validate('not a valid url');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_root_server_url', $result->get_error_code());
    }

    /**
     * A non-https URL is rejected (mirrors the client-side https-only rule).
     */
    public function testNonHttpsUrlIsRejected(): void {
        $result = RootServerValidator::validate('http://bmlt.example.com');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_root_server_url', $result->get_error_code());
    }

    /**
     * A well-formed URL whose host can't be reached is rejected.
     */
    public function testUnreachableHostIsRejected(): void {
        // Empty response map => wp_remote_get returns a WP_Error for any URL.
        $this->mockWpRemoteGet([]);

        $result = RootServerValidator::validate('https://unreachable.example.com');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('root_server_unreachable', $result->get_error_code());
    }

    /**
     * A reachable host returning a non-200 status is rejected.
     */
    public function testNon200ResponseIsRejected(): void {
        $this->mockWpRemoteGet([
            'GetServerInfo' => [
                'code' => 404,
                'body' => 'Not Found',
            ],
        ]);

        $result = RootServerValidator::validate('https://notbmlt.example.com');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('root_server_unreachable', $result->get_error_code());
    }

    /**
     * A reachable host that doesn't respond like a BMLT root server is rejected.
     */
    public function testReachableButNotBmltIsRejected(): void {
        $this->mockWpRemoteGet([
            'GetServerInfo' => [
                'code' => 200,
                'body' => ['result' => 'this is not bmlt'],
            ],
        ]);

        $result = RootServerValidator::validate('https://notbmlt.example.com');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('not_a_bmlt_root_server', $result->get_error_code());
    }
}
