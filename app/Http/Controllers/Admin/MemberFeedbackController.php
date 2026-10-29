<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberFeedbackArea;
use App\Enums\MemberFeedbackKind;
use App\Enums\MemberFeedbackStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberFeedbackFilterRequest;
use App\Http\Requests\Admin\UpdateMemberFeedbackRequest;
use App\Models\MemberFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class MemberFeedbackController extends Controller
{
    public function index(MemberFeedbackFilterRequest $request): Response
    {
        $filters = $request->safe()->only(['kind', 'area', 'status']);
        $query = MemberFeedback::query()->with('user.profile');
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        $feedback = $query->latest('id')->paginate(20, ['id', 'user_id', 'kind', 'area', 'status', 'created_at'])->withQueryString();

        return Inertia::render('Admin/Feedback/Index', [
            'feedback' => $feedback->through(fn (MemberFeedback $item): array => $this->summary($item)),
            'filters' => $filters,
            'options' => $this->options(),
        ]);
    }

    public function show(MemberFeedback $feedback): Response
    {
        Gate::authorize('view', $feedback);
        $feedback->load('user.profile');

        return Inertia::render('Admin/Feedback/Show', [
            'feedback' => [...$this->summary($feedback), 'content' => $feedback->content, 'internal_note' => $feedback->internal_note],
            'options' => $this->options(),
        ]);
    }

    public function update(UpdateMemberFeedbackRequest $request, MemberFeedback $feedback): RedirectResponse
    {
        $feedback->update($request->safe()->only(['status', 'internal_note']));

        return back();
    }

    /** @return array<string, mixed> */
    private function summary(MemberFeedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'kind' => $feedback->kind->value,
            'area' => $feedback->area->value,
            'status' => $feedback->status->value,
            'created_at' => $feedback->created_at?->toISOString(),
            'author' => ['id' => $feedback->user_id, 'name' => $feedback->user->profile->display_name ?? __('feedback.member')],
        ];
    }

    /** @return array<string, list<string>> */
    private function options(): array
    {
        return [
            'kinds' => array_column(MemberFeedbackKind::cases(), 'value'),
            'areas' => array_column(MemberFeedbackArea::cases(), 'value'),
            'statuses' => array_column(MemberFeedbackStatus::cases(), 'value'),
        ];
    }
}
