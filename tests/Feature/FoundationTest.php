<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_landing_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee(config('app.name'));
    }

    #[Test]
    public function the_health_check_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    #[Test]
    public function responses_carry_security_headers(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    #[Test]
    public function hsts_is_sent_over_https(): void
    {
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
