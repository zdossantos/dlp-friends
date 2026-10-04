<?php

use App\Actions\ReportConversation;
use App\Enums\ConversationReportReason;
use App\Models\Conversation;
use App\Models\ConversationReport;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

it('serializes simultaneous reports and creates only one open report and block', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('This lock test requires the pcntl extension.');
    }

    $members = User::factory()->withProfile()->count(2)->create();
    $conversation = MemberMatch::query()->create(['user_low_id' => $members[0]->id, 'user_high_id' => $members[1]->id])->conversation()->create();
    $control = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    $this->assertIsArray($control);
    foreach ($control as $socket) {
        stream_set_timeout($socket, 15);
    }
    $resultFile = tempnam(sys_get_temp_dir(), 'conversation-report-race-');
    $this->assertIsString($resultFile);
    DB::disconnect();

    $pid = pcntl_fork();
    $this->assertNotSame(-1, $pid);
    if ($pid === 0) {
        fclose($control[0]);
        DB::purge();
        try {
            ConversationReport::creating(static function () use ($control): void {
                fwrite($control[1], 'L');
                if (fread($control[1], 1) !== 'R') {
                    throw new RuntimeException('The participant lock was not released.');
                }
            });
            app(ReportConversation::class)->handle(
                User::query()->findOrFail($members[0]->id),
                Conversation::query()->findOrFail($conversation->id),
                ConversationReportReason::Other, null, true,
            );
            file_put_contents($resultFile, 'ok');
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultFile, $exception::class.': '.$exception->getMessage());
            exit(1);
        }
    }

    fclose($control[1]);
    $this->assertSame('L', fread($control[0], 1));
    DB::reconnect();
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
    try {
        app(ReportConversation::class)->handle($members[0], $conversation, ConversationReportReason::Other, null, true);
        $this->fail('The duplicate report must wait for the participant lock.');
    } catch (QueryException $exception) {
        $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
    }

    fwrite($control[0], 'R');
    pcntl_waitpid($pid, $status);
    fclose($control[0]);
    DB::purge();
    DB::reconnect();

    $this->assertSame(0, pcntl_wexitstatus($status), (string) file_get_contents($resultFile));
    unlink($resultFile);
    $this->assertDatabaseCount('conversation_reports', 1);
    $this->assertDatabaseCount('blocks', 1);
    expect(fn () => app(ReportConversation::class)->handle($members[0], $conversation, ConversationReportReason::Other, null, true))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('conversation_reports', 1);
});
