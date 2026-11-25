<?php

namespace Tests\Feature;

use Tests\Feature\Security\SecurityTestCase;

class ExampleTest extends SecurityTestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        \Illuminate\Support\Facades\DB::table('custom_layouts')->insert(['custom_header' => '', 'custom_body' => '', 'created_at' => now(), 'updated_at' => now()]);

        $this->withoutVite();
        $this->get('/')->assertStatus(200);
    }
}
