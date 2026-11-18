<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_the_public_attendance_page(): void
    {
        $this->get('/')->assertRedirect('/absensi');
        $this->get('/absensi')->assertOk();
    }
}
