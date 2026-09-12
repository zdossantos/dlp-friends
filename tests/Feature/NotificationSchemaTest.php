<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_can_be_stored_for_polymorphic_recipients(): void
    {
        expect(Schema::hasColumns('notifications', [
            'id',
            'type',
            'notifiable_type',
            'notifiable_id',
            'data',
            'read_at',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
    }
}
