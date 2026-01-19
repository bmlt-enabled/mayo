<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\Rest;
use Brain\Monkey\Functions;

class RestTest extends TestCase {

    /**
     * Test init registers rest_api_init action
     */
    public function testInitRegistersRestApiInit(): void {
        $actionsAdded = [];

        Functions\when('add_action')->alias(function($tag, $callback, $priority = 10) use (&$actionsAdded) {
            $actionsAdded[] = $tag;
        });

        Rest::init();

        $this->assertContains('rest_api_init', $actionsAdded);
    }

    /**
     * Test register_routes delegates to all controllers
     */
    public function testRegisterRoutesDelegatesToControllers(): void {
        // Since register_routes just calls other static methods,
        // we verify it doesn't throw an exception
        Rest::register_routes();

        $this->assertTrue(true);
    }

    /**
     * Test bmltenabled_mayo_get_events returns WP_REST_Response
     */
    public function testBmltenabledMayoGetEventsReturnsRestResponse(): void {
        Functions\when('get_posts')->justReturn([]);

        $this->mockGetOption([
            'mayo_external_sources' => []
        ]);

        $result = Rest::bmltenabled_mayo_get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
    }

    /**
     * Test bmltenabled_mayo_get_events creates request when null passed
     */
    public function testBmltenabledMayoGetEventsCreatesRequestWhenNullPassed(): void {
        Functions\when('get_posts')->justReturn([]);

        $this->mockGetOption([
            'mayo_external_sources' => []
        ]);

        $result = Rest::bmltenabled_mayo_get_events(null);

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
        $data = $result->get_data();
        $this->assertArrayHasKey('events', $data);
    }

    /**
     * Test bmltenabled_mayo_get_events returns events array in response
     */
    public function testBmltenabledMayoGetEventsReturnsEventsArray(): void {
        Functions\when('get_posts')->justReturn([]);

        $this->mockGetOption([
            'mayo_external_sources' => []
        ]);

        $result = Rest::bmltenabled_mayo_get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
        $data = $result->get_data();
        $this->assertArrayHasKey('events', $data);
        $this->assertIsArray($data['events']);
    }
}
