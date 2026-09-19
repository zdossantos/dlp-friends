<?php

namespace Tests\Unit\Rules;

use App\Rules\SafeHttpsUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafeHttpsUrlTest extends TestCase
{
    #[DataProvider('publicUrls')]
    public function test_it_accepts_public_absolute_https_urls_without_a_network_request(string $url): void
    {
        Http::preventStrayRequests();

        $validator = Validator::make(
            ['destination_url' => $url],
            ['destination_url' => [new SafeHttpsUrl]],
        );

        $this->assertTrue($validator->passes());
    }

    /** @return array<string, array{string}> */
    public static function publicUrls(): array
    {
        return [
            'public domain' => ['https://offers.example.com/path?source=dlp#details'],
            'public IPv4' => ['https://8.8.8.8/dns-query'],
            'public IPv6' => ['https://[2001:4860:4860::8888]/dns-query'],
        ];
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
            'short loopback IPv4' => ['https://127.1/path'],
            'integer loopback IPv4' => ['https://2130706433/path'],
            'octal loopback IPv4' => ['https://0177.0.0.1/path'],
            'hexadecimal loopback IPv4' => ['https://0x7f000001/path'],
            'private IPv4' => ['https://192.168.1.12/path'],
            'shared address space IPv4' => ['https://100.64.0.1/path'],
            'link-local IPv4' => ['https://169.254.1.1/path'],
            'protocol assignments IPv4' => ['https://192.0.0.1/path'],
            'deprecated relay IPv4' => ['https://192.88.99.1/path'],
            'benchmark IPv4' => ['https://198.18.0.1/path'],
            'documentation IPv4 TEST-NET-1' => ['https://192.0.2.1/path'],
            'documentation IPv4 TEST-NET-2' => ['https://198.51.100.1/path'],
            'documentation IPv4 TEST-NET-3' => ['https://203.0.113.1/path'],
            'multicast IPv4' => ['https://224.0.0.1/path'],
            'unspecified IPv4' => ['https://0.0.0.0/path'],
            'loopback IPv6' => ['https://[::1]/path'],
            'IPv4-mapped IPv6' => ['https://[::ffff:127.0.0.1]/path'],
            'private IPv6' => ['https://[fc00::1]/path'],
            'link-local IPv6' => ['https://[fe80::1]/path'],
            'discard-only IPv6' => ['https://[100::1]/path'],
            'deprecated ORCHID IPv6' => ['https://[2001:10::1]/path'],
            'ORCHIDv2 IPv6' => ['https://[2001:20::1]/path'],
            'documentation IPv6' => ['https://[2001:db8::1]/path'],
            'documentation IPv6 3fff' => ['https://[3fff::1]/path'],
            'segment-routing IPv6' => ['https://[5f00::1]/path'],
            'deprecated site-local IPv6' => ['https://[fec0::1]/path'],
            'multicast IPv6' => ['https://[ff02::1]/path'],
        ];
    }
}
