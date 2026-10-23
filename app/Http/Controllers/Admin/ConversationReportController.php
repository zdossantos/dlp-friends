<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CloseConversationReport;
use App\Data\TripDepartureReportData;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloseConversationReportRequest;
use App\Models\ConversationReport;
use App\Models\Message;
use App\Models\ModerationAudit;
use App\Models\TripDepartureReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        $target = $request->has('target') ? User::query()->with('profile')->findOrFail($request->integer('target')) : null;
        $status = $request->has('closed') ? ($request->boolean('closed') ? 'closed' : 'open') : ($target === null ? 'open' : 'all');
        $query = ConversationReport::query()->when($target, fn (Builder $query) => $query->where('target_user_id', $target->id));
        $counts = ['open' => (clone $query)->whereNull('closed_at')->count(), 'closed' => (clone $query)->whereNotNull('closed_at')->count()];
        $total = $status === 'all' ? array_sum($counts) : $counts[$status];
        $page = min(max(1, $request->integer('page', 1)), max(1, (int) ceil($total / 20)));

        return Inertia::render('Admin/ConversationReports/Index', [
            'tripReports' => TripDepartureReport::query()->when($target, fn (Builder $query) => $query->where('target_user_id', $target->id))
                ->when($status === 'closed', fn (Builder $query) => $query->whereNotNull('closed_at'))
                ->when($status === 'open', fn (Builder $query) => $query->whereNull('closed_at'))
                ->with(['reporter.profile', 'target.profile', 'target.roles'])->latest('id')->paginate(20, pageName: 'trips_page')->withQueryString()
                ->through(fn (TripDepartureReport $report): array => TripDepartureReportData::detail($report)),
            'closed' => $status === 'closed',
            'status' => $status,
            'counts' => $counts,
            'target' => $target === null ? null : ['id' => $target->id, 'name' => $target->profile->display_name ?? __('moderation.deleted_member')],
            'source' => $request->query('source') === 'profile' ? 'profile' : 'admin',
            'reports' => $query->with(['reporter.profile', 'target.profile', 'target.roles'])
                ->when($status === 'closed', fn (Builder $query) => $query->whereNotNull('closed_at'))
                ->when($status === 'open', fn (Builder $query) => $query->whereNull('closed_at'))
                ->latest('id')->paginate(20, page: $page)->withQueryString()
                ->through(fn (ConversationReport $report): array => $this->reportData($report)),
        ]);
    }

    public function show(Request $request, ConversationReport $report): Response
    {
        Gate::authorize('view', $report);
        ModerationAudit::query()->create(['actor_user_id' => $request->user()->id, 'target_user_id' => $report->target_user_id, 'conversation_report_id' => $report->id, 'operation' => 'view']);
        $context = $this->context($request);
        $listContext = $context;
        unset($listContext['list_page']);
        $listContext['page'] = $context['list_page'];

        $reportData = $this->reportData($report);
        $publicProfileUrls = [];
        foreach (['reporter' => $report->reporter, 'target' => $report->target] as $role => $member) {
            $publicProfileUrls[$role] = $member?->profile !== null && Gate::allows('viewPublic', $member->profile)
                ? route('members.show', $member, absolute: false)
                : null;
        }

        return Inertia::render('Admin/ConversationReports/Show', [
            'report' => $reportData,
            'publicProfileUrls' => $publicProfileUrls,
            'context' => $context,
            'backHref' => route('admin.conversation-reports.index', $listContext, absolute: false),
            'messages' => $report->conversation->messages()->orderBy('created_at')->orderBy('id')->paginate(50)->withQueryString()->through(fn (Message $message): array => ['id' => $message->id, 'author_user_id' => $message->author_user_id, 'content' => $message->content, 'created_at' => $message->created_at?->toISOString()]),
        ]);
    }

    public function close(CloseConversationReportRequest $request, ConversationReport $report, CloseConversationReport $action): RedirectResponse
    {
        $action->handle($request->user(), $report, $request->string('decision')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('moderation.report_closed')]);

        return redirect()->route('admin.conversation-reports.show', ['report' => $report, ...$this->context($request)]);
    }

    /** @return array<string, int|bool|string> */
    private function context(Request $request): array
    {
        return [
            ...($request->has('target') ? ['target' => $request->integer('target')] : []),
            ...($request->has('closed') ? ['closed' => $request->boolean('closed')] : []),
            ...($request->query('source') === 'profile' ? ['source' => 'profile'] : []),
            'list_page' => max(1, $request->integer('list_page', 1)),
        ];
    }

    /** @return array<string, mixed> */
    private function reportData(ConversationReport $report): array
    {
        $report->loadMissing(['reporter.profile', 'target.profile', 'target.roles']);

        return ['id' => $report->id, 'conversation_id' => $report->conversation_id, 'reason' => $report->reason->value, 'details' => $report->details, 'decision' => $report->decision, 'closed_at' => $report->closed_at?->toISOString(), 'created_at' => $report->created_at?->toISOString(), 'reporter' => ['id' => $report->reporter_user_id, 'name' => $report->reporter?->profile->display_name ?? __('moderation.deleted_member')], 'target' => ['id' => $report->target_user_id, 'name' => $report->target?->profile->display_name ?? __('moderation.deleted_member'), 'banned' => $report->target?->status === UserStatus::Banned, 'can_ban' => $report->target !== null && Gate::allows('ban', $report->target)]];
    }
}
