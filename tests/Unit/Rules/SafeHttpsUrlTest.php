<?php

namespace Tests\Unit\Rules;

use App\Rules\SafeHttpsUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafeHttpsUrlTest extends TestCase
{
    public function test_it_accepts_a_public_absolute_https_url_without_a_network_request(): void
    {
        Http::preventStrayRequests();

        $validator = Validator::make(
            ['destination_url' => 'https://offers.example.com/path?source=dlp#details'],
            ['destination_url' => [new SafeHttpsUrl]],
        );

        $this->assertTrue($validator->passes());
    }

    #[DataProvider('unsafeUrls')]
    public function test_it_rejects_unsafe_destinations(string $url): void
    {
        $validator = Validator::make(
            ['destination_url' => $url],
            ['destination_url' => [new SafeHttpsUrl]],
        );

        $this->assertTrue($validator->fails(), $url);
    }

    /** @return array<string, array{string}> */
    public static function unsafeUrls(): array
    {
        return [
            'relative URL' => ['/offers'],
            'HTTP URL' => ['http://offers.example.com/path'],
            'JavaScript URL' => ['javascript:alert(1)'],
            'embedded username' => ['https://user@offers.example.com/path'],
            'embedded password' => ['https://user:secret@offers.example.com/path'],
            'localhost' => ['https://localhost/path'],
            'localhost subdomain' => ['https://preview.localhost/path'],
            'local domain' => ['https://printer.local/path'],
            'loopback IPv4' => ['https://127.0.0.1/path'],
            'private IPv4' => ['https://192.168.1.12/path'],
            'link-local IPv4' => ['https://169.254.1.1/path'],
            'unspecified IPv4' => ['https://0.0.0.0/path'],
            'loopback IPv6' => ['https://[::1]/path'],
            'private IPv6' => ['https://[fc00::1]/path'],
            'link-local IPv6' => ['https://[fe80::1]/path'],
        ];
    }
}
