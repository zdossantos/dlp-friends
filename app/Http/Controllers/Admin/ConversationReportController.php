<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CloseConversationReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloseConversationReportRequest;
use App\Models\ConversationReport;
use App\Models\Message;
use App\Models\ModerationAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ConversationReportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ConversationReport::class);
        $closed = $request->boolean('closed');

        return Inertia::render('Admin/ConversationReports/Index', ['closed' => $closed, 'reports' => ConversationReport::query()->when($closed, fn ($q) => $q->whereNotNull('closed_at'), fn ($q) => $q->whereNull('closed_at'))->latest('id')->paginate(20)->withQueryString()->through(fn (ConversationReport $report): array => $this->reportData($report))]);
    }

    public function show(Request $request, ConversationReport $report): Response
    {
        Gate::authorize('view', $report);
        ModerationAudit::query()->create(['actor_user_id' => $request->user()->id, 'target_user_id' => $report->target_user_id, 'conversation_report_id' => $report->id, 'operation' => 'view']);

        return Inertia::render('Admin/ConversationReports/Show', ['report' => $this->reportData($report), 'messages' => $report->conversation->messages()->orderBy('created_at')->orderBy('id')->paginate(50)->through(fn (Message $message): array => ['id' => $message->id, 'author_user_id' => $message->author_user_id, 'content' => $message->content, 'created_at' => $message->created_at?->toISOString()])]);
    }

    public function close(CloseConversationReportRequest $request, ConversationReport $report, CloseConversationReport $action): RedirectResponse
    {
        $action->handle($request->user(), $report, $request->string('decision')->toString());

        return redirect()->route('admin.conversation-reports.show', $report);
    }

    /** @return array<string, mixed> */
    private function reportData(ConversationReport $report): array
    {
        $report->loadMissing(['reporter.profile', 'target.profile']);

        return ['id' => $report->id, 'conversation_id' => $report->conversation_id, 'reason' => $report->reason->value, 'details' => $report->details, 'decision' => $report->decision, 'closed_at' => $report->closed_at?->toISOString(), 'created_at' => $report->created_at?->toISOString(), 'reporter' => ['id' => $report->reporter_user_id, 'name' => $report->reporter?->profile->display_name ?? __('moderation.deleted_member')], 'target' => ['id' => $report->target_user_id, 'name' => $report->target?->profile->display_name ?? __('moderation.deleted_member')]];
    }
}
