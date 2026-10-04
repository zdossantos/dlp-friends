<?php

use App\Actions\DeleteMember;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(DatabaseMigrations::class);

it('retains the current banned status after a deletion established an older snapshot', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('This lock test requires the pcntl extension.');
    }
    Mail::fake();
    $other = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    $conversation = MemberMatch::query()->create(['user_low_id' => $other->id, 'user_high_id' => $target->id])->conversation()->create();
    $message = $conversation->messages()->create(['author_user_id' => $target->id, 'content' => 'Retain after concurrent ban']);
    $control = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    $this->assertIsArray($control);
    foreach ($control as $socket) {
        stream_set_timeout($socket, 15);
    }
    DB::disconnect();
    $pid = pcntl_fork();
    $this->assertNotSame(-1, $pid);
    if ($pid === 0) {
        fclose($control[0]);
        DB::purge();
        if (fread($control[1], 1) !== 'S') {
            exit(1);
        }
        User::query()->whereKey($target->id)->update(['status' => 'banned']);
        fwrite($control[1], 'B');
        exit(0);
    }
    fclose($control[1]);
    DB::reconnect();
    $paused = false;
    DB::connection()->beforeExecuting(function (string $query) use (&$paused, $control): void {
        if (! $paused && str_contains($query, 'from `users` where `users`.`id` in') && str_contains($query, 'for update')) {
            $paused = true;
            fwrite($control[0], 'S');
            expect(fread($control[0], 1))->toBe('B');
        }
    });
    try {
        app(DeleteMember::class)->handle($other);
        expect($paused)->toBeTrue();
        $this->assertDatabaseHas('messages', ['id' => $message->id, 'author_user_id' => $target->id]);
        $this->assertDatabaseHas('matches', ['id' => $conversation->match_id, 'user_low_id' => null, 'user_high_id' => $target->id]);
    } finally {
        fclose($control[0]);
        pcntl_waitpid($pid, $status);
        DB::purge();
    }
});
