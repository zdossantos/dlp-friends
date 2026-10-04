<?php

namespace App\Http\Controllers;

use App\Actions\ReportConversation;
use App\Enums\ConversationReportReason;
use App\Http\Requests\StoreConversationReportRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class ConversationReportController extends Controller
{
    public function __invoke(StoreConversationReportRequest $request, Conversation $conversation, ReportConversation $action): RedirectResponse
    {
        $data = $request->validated();
        $action->handle($request->user(), $conversation, ConversationReportReason::from($data['reason']), $data['details'] ?? null, $request->boolean('block'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('moderation.reported')]);

        return redirect()->route('conversations.index');
    }
}
