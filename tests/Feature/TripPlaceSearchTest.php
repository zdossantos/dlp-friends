<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

test('address suggestions require an eligible authenticated member', function () {
    $this->postJson('/trips/places', ['query' => 'Paris'])->assertUnauthorized();
    $this->actingAs(User::factory()->withProfile()->create(['status' => 'banned']))->postJson('/trips/places', ['query' => 'Paris'])->assertForbidden();
    Http::assertNothingSent();
});

test('address suggestions validate input before contacting the provider', function (mixed $query) {
    $this->actingAs(User::factory()->withProfile()->create())->postJson('/trips/places', ['query' => $query])->assertUnprocessable()->assertJsonValidationErrors('query');
    Http::assertNothingSent();
})->with(['Pa', str_repeat('a', 161), null, [['Paris']]]);

test('address suggestions send only search and language and return minimal deduplicated labels', function () {
    Http::fake(['photon.komoot.io/*' => Http::response(['features' => [
        ['properties' => ['name' => 'Gare de Lyon', 'street' => 'Place Louis-Armand', 'postcode' => '75012', 'city' => 'Paris', 'country' => 'France', 'osm_id' => 123]],
        ['properties' => ['name' => 'Gare de Lyon', 'street' => 'Place Louis-Armand', 'postcode' => '75012', 'city' => 'Paris', 'country' => 'France']],
        ['properties' => ['name' => ['invalid']]],
    ]])]);
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user)->postJson('/trips/places', ['query' => 'Gare de Lyon', 'email' => $user->email, 'meeting_point' => 'secret'])->assertOk()->assertExactJson(['suggestions' => ['Gare de Lyon, Place Louis-Armand, 75012 Paris, France']])->assertHeader('Cache-Control', 'no-store, private');
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://photon.komoot.io/api/?') && $request->data() === ['q' => 'Gare de Lyon', 'lang' => 'fr', 'limit' => 5] && ! $request->hasHeader('Authorization') && ! $request->hasHeader('Cookie'));
    Http::assertSentCount(1);
});

test('address suggestions fail gracefully when provider is unavailable or malformed', function (mixed $body, int $status) {
    Http::fake(['*' => Http::response($body, $status)]);
    $this->actingAs(User::factory()->withProfile()->create())->postJson('/trips/places', ['query' => 'Paris'])->assertStatus(503)->assertExactJson(['suggestions' => []]);
})->with([[[], 500], ['not json', 200], [['features' => 'invalid'], 200]]);

test('address suggestions handle connection failures without exposing the request', function () {
    Http::fake(['*' => Http::failedConnection()]);
    $this->actingAs(User::factory()->withProfile()->create())->postJson('/trips/places', ['query' => 'Paris'])->assertStatus(503)->assertExactJson(['suggestions' => []]);
});

test('address suggestions respect English and bound the number and length of results', function () {
    Http::fake(['*' => Http::response(['features' => array_map(fn (int $i) => ['properties' => ['name' => $i.str_repeat('a', 200)]], range(1, 8))])]);
    $response = $this->actingAs(User::factory()->withProfile()->create(['locale' => 'en']))->postJson('/trips/places', ['query' => 'London'])->assertOk()->assertJsonCount(5, 'suggestions');
    foreach ($response->json('suggestions') as $label) {
        expect(mb_strlen($label))->toBe(160);
    }
    Http::assertSent(fn (Request $request) => $request['lang'] === 'en');
});
