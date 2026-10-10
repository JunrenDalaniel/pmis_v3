<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Test that the root URL redirects to the admin panel.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Route '/' is registered to redirect to admin.
        // Route matching uses URI (always '/'), not APP_URL subdirectory prefix.
        $response = $this->get('/');

        $response->assertRedirectContains('admin');
    }
}
