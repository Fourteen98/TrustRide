<?php

namespace App\Api\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Support\Actions\RaiseAlert;
use Modules\Support\Enums\CaseCategory;
use Modules\Support\Enums\CaseStatus;
use Modules\Support\Models\SupportCase;

class SupportController
{
    /**
     * Her conversation with Aveo.
     *
     * The open case if she has one, otherwise one resolved in the last few days so she can read the answer.
     * Null if there is neither. Ask again every so often while a case is open, to pick up replies.
     */
    public function show(Request $request): JsonResponse
    {
        $case = SupportCase::query()
            ->where('account_id', $request->user()->id)
            ->where(fn ($query) => $query
                ->where('status', CaseStatus::Open)
                ->orWhere('resolved_at', '>=', now()->subDays((int) config('support.show_resolved_days'))))
            ->with('entries')
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->latest('id')
            ->first();

        return response()->json([
            'case' => $case ? new CaseResource($case) : null,
            /**
             * What she can say it is about when she starts a new conversation.
             *
             * @var list<array{key: string, label: string}>
             */
            'categories' => collect(CaseCategory::cases())->map(fn (CaseCategory $category) => ['key' => $category->value, 'label' => $category->label()])->all(),
            'support' => [
                /** @var string|null */
                'phone' => config('aveo.support.phone'),
                /** @var string|null */
                'whatsapp' => config('aveo.support.whatsapp'),
            ],
        ]);
    }

    /**
     * Write to Aveo.
     *
     * Her first message opens a case and needs a category. Anything she sends while it is open is added to it.
     * "I don't feel safe" is treated as an incident at once.
     */
    public function store(Request $request, RaiseAlert $raise, Reporter $reporter): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            /** Only used when there is no open case. One of the keys under `categories`. */
            'category' => ['nullable', Rule::enum(CaseCategory::class)],
        ]);

        $case = $raise->handle(
            $request->user(),
            $data['message'],
            isset($data['category']) ? CaseCategory::from($data['category']) : null,
            $reporter->describe($request->user()),
        );

        return response()->json(['case' => new CaseResource($case->load('entries'))], 201);
    }
}
