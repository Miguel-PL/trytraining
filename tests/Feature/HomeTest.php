<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeTest extends TestCase
{
    public function test_root_page_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Try Training');
    }

    public function test_health_check_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
