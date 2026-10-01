<?php

use Livewire\Component;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Ai\AiMessage;
use App\Models\Ai\AiConversation;
use App\Services\Ai\AiService;
use App\Services\Ai\McpFunctionRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    /** Current conversation being displayed */
    public ?int $conversationId = null;

    /** Current user message being typed */
    public string $message = '';

    /**
     * In-memory messages for the active conversation UI.
     * Loaded fresh from DB on conversation switch.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $history = [];

    /** True while AI is generating */
    public bool $thinking = false;

    /** Error message from last failed call */
    public ?string $error = null;

    // ─────────────────────────────────────────────────────────────────────────

    public function with(): array
    {
        return [
            'conversations' => AiConversation::where('user_id', Auth::id())
                ->latest()
                ->limit(30)
                ->get(),
        ];
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        // Load most recent conversation or create a new one
        $conversation = AiConversation::where('user_id', $user->id)
            ->latest()
            ->first();

        if ($conversation) {
            $this->loadConversation($conversation->id);
        } else {
            $this->newConversation();
        }
    }

    /**
     * Load a specific conversation by ID.
     */
    public function loadConversation(int $id): void
    {
        /** @var User $user */
        $user = Auth::user();

        $conversation = AiConversation::where('user_id', $user->id)->findOrFail($id);
        $this->conversationId = $conversation->id;
        $this->error = null;

        // Build history from DB messages
        $this->history = $conversation->messages
            ->whereIn('role', ['user', 'assistant'])
            ->map(fn(AiMessage $m) => [
                'role'    => $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->toArray();

        // Add greeting if empty
        if ($this->history === []) {
            $this->history = [['role' => 'assistant', 'content' => $this->greeting()]];
        }
    }

    /**
     * Start a fresh conversation.
     */
    public function newConversation(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'title'   => 'New Conversation',
            'model'   => config('deepseek.model', 'deepseek-chat'),
        ]);

        $this->conversationId = $conversation->id;
        $this->error = null;
        $this->history = [['role' => 'assistant', 'content' => $this->greeting()]];
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(int $id): void
    {
        /** @var User $user */
        $user = Auth::user();

        AiConversation::where('user_id', $user->id)->where('id', $id)->delete();

        if ($this->conversationId === $id) {
            $this->newConversation();
        }
    }

    /**
     * Send a message and get an AI response.
     */
    public function send(): void
    {
        $text = trim($this->message);
        if ($text === '' || $this->conversationId === null) {
            return;
        }

        $this->error   = null;
        $this->message = '';

        // Add user message to UI + DB
        $this->history[] = ['role' => 'user', 'content' => $text];
        $this->thinking  = true;

        AiMessage::create([
            'ai_conversation_id' => $this->conversationId,
            'role'               => 'user',
            'content'            => $text,
        ]);

        // Update conversation title from the first user message
        $conversation = AiConversation::find($this->conversationId);
        if ($conversation && $conversation->title === 'New Conversation') {
            $conversation->update(['title' => mb_substr($text, 0, 60)]);
        }

        // Placeholder for assistant response
        $this->history[] = ['role' => 'assistant', 'content' => '', 'typing' => true];

        $this->js('$wire.processAi()');
    }

    public function processAi(): void
    {
        if (!$this->thinking || $this->conversationId === null) {
            return;
        }

        $lastIndex = count($this->history) - 1;
        $userMessageIndex = $lastIndex - 1;
        $text = $this->history[$userMessageIndex]['content'] ?? '';

        try {
            /** @var User $admin */
            $admin    = Auth::user();
            $registry = new McpFunctionRegistry($admin);
            $deepseek = app(AiService::class);
            $tools    = $registry->definitions();

            $messages = $this->buildApiMessages($text);
            $accumulated = '';
            $totalPrompt = 0;
            $totalCompletion = 0;

            $result = $deepseek->chat($messages, $tools, function (string $chunk) use ($lastIndex, &$accumulated): void {
                $accumulated .= $chunk;
                $this->history[$lastIndex]['content'] = $accumulated;
            });

            $totalPrompt     += $result['prompt_tokens'];
            $totalCompletion += $result['completion_tokens'];

            // Tool-call loop
            $loopMessages = $messages;
            $loopMessages[] = [
                'role'       => 'assistant',
                'content'    => $result['content'],
                'tool_calls' => $result['tool_calls'],
            ];

            $iterations = 0;
            while ($result['tool_calls'] !== [] && $iterations < 3) {
                $iterations++;

                foreach ($result['tool_calls'] as $tc) {
                    $fnName     = $tc['function']['name'] ?? '';
                    $fnArgs     = json_decode($tc['function']['arguments'] ?? '{}', true) ?? [];
                    $toolResult = $registry->dispatch($fnName, $fnArgs);

                    AiMessage::create([
                        'ai_conversation_id' => $this->conversationId,
                        'role'               => 'tool',
                        'content'            => json_encode($toolResult),
                        'tool_name'          => $fnName,
                    ]);

                    $loopMessages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $tc['id'],
                        'content'      => json_encode($toolResult, JSON_UNESCAPED_SLASHES),
                    ];
                }

                $accumulated = '';
                $result = $deepseek->chat($loopMessages, $tools, function (string $chunk) use ($lastIndex, &$accumulated): void {
                    $accumulated .= $chunk;
                    $this->history[$lastIndex]['content'] = $accumulated;
                });

                $totalPrompt     += $result['prompt_tokens'];
                $totalCompletion += $result['completion_tokens'];

                $loopMessages[] = [
                    'role'       => 'assistant',
                    'content'    => $result['content'],
                    'tool_calls' => $result['tool_calls'],
                ];
            }

            // Persist assistant message
            $finalContent = $accumulated ?: '*(no response)*';
            $this->history[$lastIndex]['typing']  = false;
            $this->history[$lastIndex]['content'] = $finalContent;

            AiMessage::create([
                'ai_conversation_id' => $this->conversationId,
                'role'               => 'assistant',
                'content'            => $finalContent,
                'prompt_tokens'      => $totalPrompt,
                'completion_tokens'  => $totalCompletion,
            ]);

            // Update conversation token totals
            AiConversation::where('id', $this->conversationId)->increment('total_prompt_tokens', $totalPrompt);
            AiConversation::where('id', $this->conversationId)->increment('total_completion_tokens', $totalCompletion);
        } catch (\Throwable $e) {
            Log::error('[AiAssistantPage] error', ['message' => $e->getMessage()]);
            $this->history[$lastIndex]['typing']  = false;
            $this->history[$lastIndex]['content'] = '⚠️ Sorry, I encountered an error. Please try again.';
            $this->error = $e->getMessage();
        }

        $this->thinking = false;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildApiMessages(string $latestUserMessage): array
    {
        /** @var User $admin */
        $admin = Auth::user();

        $systemPrompt = <<<PROMPT
            Ikaw si **Ben** — ang AI assistant sa LYR Pickle Club, usa ka premium pickleball court booking platform sa Pilipinas.
            Nag-tabang ka karon kang **{$admin->name}** (role: {$admin->role}) sa admin dashboard.

            ## Imong Personalidad
            - Ang imong permanenteng ngalan kay **Ben** originated as Benedict Lu the creator sa LYR Pickle Club System.
            - Kung mangutana sila kinsa nag buhat nimo ingon: "Ako si Ben, gihimo ni Benedict Lu para sa LYR Pickle Club ang gwapo nga developer sa LYR Marketing and Furniture Center."
            -kung mangutana sila kinsa si benedict, tubaga: "Si Benedict Lu ang developer and nagbuhat nako para sa LYR Pickle Club ang gwapo nga developer LYR Marketing and Furniture Center"
            -kung mangutana sila sa ubang mga unrelevant nga pangutana bahin sa imong creator, wala niya gi tugot nga mag-reply sa iyang personal information."
            -kung mangutana sila sa mga unrelevant nga pangutana gawas sa pickleball booking, revenue, courts, o related sa LYR Pickle Club, tubaga: "Pasensya na, dili ko makahatag ug impormasyon bahin ana. Ang akong focus kay sa LYR Pickle Club ug sa imong mga pangutana bahin sa bookings, revenue, ug courts."
            - Kung mangutana sila "unsay imong ngalan?", "what is your name?", o parehas ana, tubaga una ug eksakto: **"Ako si Ben!"** Ayaw gamita ang ngalan sa admin isip imong kaugalingong ngalan.
            - Kung ang pangutana kay bahin ra sa imong ngalan, ayaw pagdugang ug booking, revenue, o laing impormasyon gawas kung gipangutana usab.
            - Magsulti ka sa **natural nga Bisaya-English code-switching** — sama sa tinuod nga taga-Cebu/Visayas nga nagsulti.
            - Gamiton ang mga Bisaya/Cebuano expressions sama sa: "ay sus", "bitaw", "basta", "lagi", "mao", "uy", "naa", "wala", "ayaw", "dali lang", "sus mare/pare", "ay teh", "oy" — natural ra, dili sobra.
            - Ang tono: **mainit, friendly, relaks pero helpful** — like a Bisaya officemate nga maayo kaayo.
            - Mix Bisaya ug English naturally. Dili mo mag-translate ug buong sentences — mix lang pareho sa tinuod nga pagsulti.
            - Kung mag-apologize: "ay sus sorry ha", "ay nako", "pasensya na"
            - Kung maghatag ug maayo nga balita: "uy nindot!", "ay mao diay!", "lami kaayo na results"
            - Kung mag-confirm action: "okay lagi!", "done na nimo boss", "naa na"
            - Dili ka mag-sound robotic o formal kaayo — human accent.

            ## Imong Capabilities
            - Naa kay access sa **live database data** pinaagi sa function calls (bookings, courts, revenue).
            - Kanunay mag-call sa function una sa pagtubag sa data questions — ayaw tag-guess o mag-fabricate.
            - Makaupdate ka ug booking status kung gi-instruct ka explicitly sa admin.

            ## Response Rules
            - I-format ang currency as **₱X,XXX.00** (Philippine Pesos).
            - I-format ang dates as **Mon, Sep 30 2026**.
            - Gamita ang markdown bold para sa important values, bullet lists para sa multiple items.
            - Ayaw buhata ang destructive actions kung wala klaro nga instruksyon.
            - Keep it natural — dili sobra formal, dili sobra pa-cute. Real lang.

            ## Context
            Karon nga adlaw kay **{$this->todayFormatted()}** (Manila time).
            Naa ka sa admin dashboard sa LYR Pickle Club.
            PROMPT;

        $apiMessages = [['role' => 'system', 'content' => $systemPrompt]];

        // Include recent conversation history (last 12 messages)
        $recent = array_filter(
            array_slice($this->history, 0, -1),
            fn($m) => in_array($m['role'] ?? '', ['user', 'assistant'], true) && ($m['content'] ?? '') !== ''
        );
        foreach (array_slice(array_values($recent), -12) as $m) {
            $apiMessages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        $apiMessages[] = ['role' => 'user', 'content' => $latestUserMessage];

        return $apiMessages;
    }

    private function greeting(): string
    {
        /** @var User $user */
        $user = Auth::user();
        $firstName = explode(' ', $user->name)[0];
        return "Kumusta 👋 **{$firstName}**! Ako si **Ben**, imong AI assistant diri sa LYR Agro Inland Resort Dashboard. " .
            "Naa koy access sa imong live data — bookings, courts, revenue — tanan! " .
            "Unsa may gusto nimo hibaloan karon?";
    }

    private function todayFormatted(): string
    {
        return Carbon::now('Asia/Manila')->format('D, M d Y');
    }

    // ─────────────────────────────────────────────────────────────────────────

    // public function render(): \Illuminate\View\View
    // {
    //     /** @var User $user */
    //     $user = Auth::user();

    //     // Usage stats
    //     $allConvs = AiConversation::where('user_id', $user->id)->get();
    //     $usageStats = [
    //         'total_conversations'   => $allConvs->count(),
    //         'total_prompt_tokens'   => $allConvs->sum('total_prompt_tokens'),
    //         'total_completion_tokens' => $allConvs->sum('total_completion_tokens'),
    //         'total_tokens'          => $allConvs->sum('total_prompt_tokens') + $allConvs->sum('total_completion_tokens'),
    //         'estimated_cost_php'    => ($allConvs->sum('total_prompt_tokens') + $allConvs->sum('total_completion_tokens')) * 0.000000075 * 56,
    //         'model'                 => config('deepseek.model'),
    //     ];

    //     // Sidebar: recent conversations (last 30)
    //     $this->conversations = AiConversation::where('user_id', $user->id)
    //         ->latest()
    //         ->limit(30)
    //         ->get();

    //     return view('livewire.admin.ai-assistant-page', [
    //         'conversations' => $this->conversations,
    //         'usageStats'    => $usageStats,
    //     ]);
    // }
};
?>

<div>
    <div
        class="flex h-[calc(90vh-4rem)] overflow-hidden"
        x-data="{
            autoScroll() {
                this.$nextTick(() => {
                    const el = this.$refs.chatMessages;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },
            observeMessages() {
                const el = this.$refs.chatMessages;
                if (el) {
                    this.messageObserver = new MutationObserver(() => this.autoScroll());
                    this.messageObserver.observe(el, { childList: true, subtree: true, characterData: true });
                }
            },
            resetTextarea() {
                this.$nextTick(() => {
                    const ta = this.$refs.chatInput;
                    if (ta) { ta.style.height = 'auto'; ta.style.height = '44px'; }
                });
            }
        }"
        x-init="observeMessages(); autoScroll()"
    >

        {{-- ══════════════════════════════════════════════════════════
             LEFT SIDEBAR — Conversations + Usage Stats
        ═══════════════════════════════════════════════════════════════ --}}
        {{-- Desktop sidebar — hidden on mobile --}}
        <aside class="hidden md:flex w-72 flex-shrink-0 flex-col border-r border-slate-200 dark:border-slate-800
                       bg-slate-50 dark:bg-dark-800 overflow-hidden">

            {{-- Usage Stats Header --}}
            {{-- <div class="px-4 py-4 border-b border-slate-200 dark:border-slate-800
                        bg-gradient-to-br from-violet-600 to-indigo-700 text-white"> --}}
                {{-- <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-violet-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span class="text-sm font-bold">Usage Statistics</span>
                </div> --}}

                {{-- <div class="grid grid-cols-2 gap-2">
                    <div class="bg-white/10 rounded-xl px-3 py-2">
                        <p class="text-[10px] text-white/60 uppercase tracking-wide">Chats</p>
                        <p class="text-lg font-black leading-none">{{ number_format($usageStats['total_conversations']) }}</p>
                    </div>
                    <div class="bg-white/10 rounded-xl px-3 py-2">
                        <p class="text-[10px] text-white/60 uppercase tracking-wide">Tokens Used</p>
                        <p class="text-lg font-black leading-none">{{ number_format($usageStats['total_tokens']) }}</p>
                    </div>
                    <div class="bg-white/10 rounded-xl px-3 py-2">
                        <p class="text-[10px] text-white/60 uppercase tracking-wide">Input</p>
                        <p class="text-sm font-bold">{{ number_format($usageStats['total_prompt_tokens']) }}</p>
                    </div>
                    <div class="bg-white/10 rounded-xl px-3 py-2">
                        <p class="text-[10px] text-white/60 uppercase tracking-wide">Output</p>
                        <p class="text-sm font-bold">{{ number_format($usageStats['total_completion_tokens']) }}</p>
                    </div>
                </div> --}}

                {{-- Estimated cost --}}
                {{-- <div class="mt-3 bg-white/10 rounded-xl px-3 py-2 flex items-center justify-between">
                    <span class="text-[10px] text-white/60 uppercase tracking-wide">Est. Cost</span>
                    <span class="text-sm font-black text-amber-300">
                        ₱{{ number_format($usageStats['estimated_cost_php'], 4) }}
                    </span>
                </div> --}}

                {{-- Model badge --}}
                {{-- <div class="mt-2 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-[10px] text-white/50 font-mono truncate">{{ $usageStats['model'] }}</span>
                </div> --}}
            {{-- </div> --}}

            {{-- New Chat Button --}}
            <div class="px-3 py-3 border-b border-dark-200 dark:border-dark-800">
                <button
                    wire:click="newConversation"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl
                           bg-lime-900 hover:bg-lime-800 text-white text-sm font-semibold
                           transition-all active:scale-95"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Conversation
                </button>
            </div>

            {{-- Conversation List --}}
            <div class="flex-1 overflow-y-auto py-2 px-2 space-y-1">
                @forelse($conversations ?? [] as $conv)
                    <div
                        wire:click="loadConversation({{ $conv->id }})"
                        class="group flex items-start gap-2 px-3 py-2.5 rounded-xl cursor-pointer transition-colors
                               {{ $conversationId === $conv->id
                                    ? 'bg-lime-100 dark:bg-dark-900/40 text-lime-700 dark:text-lime-300'
                                    : 'hover:bg-dark-100 dark:hover:bg-dark-800 text-dark-700 dark:text-dark-300' }}"
                    >
                        <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium truncate leading-snug">
                                {{ $conv->title }}
                            </p>
                            <p class="text-[10px] opacity-50 mt-0.5">
                                {{ $conv->created_at->diffForHumans() }} · {{ number_format($conv->totalTokens()) }} tok
                            </p>
                        </div>
                        {{-- Delete button --}}
                        <button
                            wire:click.stop="deleteConversation({{ $conv->id }})"
                            class="opacity-0 group-hover:opacity-100 flex-shrink-0 p-0.5 rounded hover:text-red-500 transition-all"
                            title="Delete"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 dark:text-slate-500 text-center py-6 px-3">
                        No conversations yet.<br>Start a new chat above.
                    </p>
                @endforelse
            </div>
        </aside>

        {{-- ══════════════════════════════════════════════════════════
             MAIN CHAT AREA
        ═══════════════════════════════════════════════════════════════ --}}
        {{-- Mobile slide drawer for conversations --}}
        <x-ts-slide id="conversations-slide" title="Conversations" left size="sm" paddingless>
            <div class="flex flex-col h-full">
                {{-- New Chat Button --}}
                <div class="px-3 py-3 border-b border-slate-200 dark:border-slate-700">
                    <button
                        wire:click="newConversation"
                        x-on:click="$tsui.close.slide('conversations-slide')"
                        class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl
                               bg-lime-900 hover:bg-lime-800 text-white text-sm font-semibold
                               transition-all active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Conversation
                    </button>
                </div>
                {{-- Conversation list inside slide --}}
                <div class="overflow-y-auto py-2 px-2 space-y-1">
                    @forelse($conversations ?? [] as $conv)
                        <div
                            wire:click="loadConversation({{ $conv->id }})"
                            x-on:click="$tsui.close.slide('conversations-slide')"
                            class="group flex items-start gap-2 px-3 py-2.5 rounded-xl cursor-pointer transition-colors
                                   {{ $conversationId === $conv->id
                                        ? 'bg-lime-100 dark:bg-dark-900/40 text-lime-700 dark:text-lime-300'
                                        : 'hover:bg-slate-100 dark:hover:bg-dark-800 text-dark-700 dark:text-dark-300' }}"
                        >
                            <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium truncate leading-snug">{{ $conv->title }}</p>
                                <p class="text-[10px] opacity-50 mt-0.5">
                                    {{ $conv->created_at->diffForHumans() }} · {{ number_format($conv->totalTokens()) }} tok
                                </p>
                            </div>
                            <button
                                wire:click.stop="deleteConversation({{ $conv->id }})"
                                class="opacity-0 group-hover:opacity-100 flex-shrink-0 p-0.5 rounded hover:text-red-500 transition-all"
                                title="Delete"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 dark:text-slate-500 text-center py-6 px-3">
                            No conversations yet.<br>Start a new chat above.
                        </p>
                    @endforelse
                </div>
            </div>
        </x-ts-slide>

        <div class="flex-1 flex flex-col min-w-0 bg-white dark:bg-slate-950">

            {{-- Chat header --}}
            <div class="flex-shrink-0 flex items-center gap-3 px-4 md:px-6 py-4 border-b border-slate-200 dark:border-slate-800">

                {{-- Mobile: hamburger to open conversations slide --}}
                <button
                    class="md:hidden flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center
                           bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300
                           hover:bg-lime-100 dark:hover:bg-lime-900/30 transition-colors"
                    x-on:click="$tsui.open.slide('conversations-slide')"
                    title="Conversations"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-lime-500 to-lime-600
                            flex items-center justify-center flex-shrink-0">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-base font-bold text-dark-900 dark:text-white truncate">
                        @if($conversationId)
                            {{ $conversations->firstWhere('id', $conversationId)?->title ?? 'Chat with Ben' }}
                        @else
                            Chat with Ben
                        @endif
                    </h1>
                    <p class="text-xs text-dark-400 dark:text-dark-500">
                        Ben · Bisaya-English AI · Live data access · Admin only
                    </p>
                </div>
                @if($thinking)
                    <div class="flex items-center gap-2 text-xs text-lime-500 dark:text-lime-400 font-medium">
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span class="hidden sm:inline">Dali lang, ga-huna-huna pa si Ben...</span>
                    </div>
                @endif
            </div>

            {{-- Messages area --}}
            <div id="chat-messages" x-ref="chatMessages" class="flex-1 overflow-y-auto px-6 py-6 space-y-6">

                {{-- Quick suggestions — shown only when first message --}}
                @if(count($history) <= 1)
                    <div class="flex flex-col items-center justify-center py-8 space-y-4">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-lime-500 to-lime-600
                                    flex items-center justify-center shadow-lg shadow-lime-500/20">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                            </svg>

                        </div>
                        <div class="text-center">
                            <h2 class="text-lg font-bold text-slate-800 dark:text-white">Kumusta! Ako si Ben. 👋</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Naa koy access sa imong live data — courts, bookings, ug revenue. Unsa may gusto nimo hibaloan?</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 w-full max-w-lg mt-2">
                            @foreach([
                                ['icon' => '📊', 'text' => 'Ipakita ang dashboard stats'],
                                ['icon' => '⏳', 'text' => 'Pila ka pending bookings karon?'],
                                ['icon' => '💰', 'text' => 'Pila ang revenue this month?'],
                                ['icon' => '🏟️', 'text' => 'Available ba ang courts karon?'],
                            ] as $s)
                            <button
                                wire:click="$set('message', '{{ $s['text'] }}')"
                                class="flex items-center gap-2 px-4 py-3 rounded-xl text-sm text-left
                                       border border-slate-200 dark:border-slate-700
                                       hover:border-lime-400 dark:hover:border-lime-500
                                       hover:bg-lime-50 dark:hover:bg-lime-900/20
                                       text-slate-700 dark:text-slate-300 transition-all"
                            >
                                <span>{{ $s['icon'] }}</span>
                                <span>{{ $s['text'] }}</span>
                            </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @foreach($history as $msg)
                    @if($msg['role'] === 'user')
                        {{-- User message --}}
                        <div class="flex justify-end">
                            <div class="max-w-[70%] px-4 py-3 rounded-2xl rounded-br-sm
                                        bg-lime-900 text-white text-sm leading-relaxed shadow-sm">
                                {{ $msg['content'] }}
                            </div>
                        </div>
                    @elseif($msg['role'] === 'assistant')
                        {{-- Assistant message --}}
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-xl bg-gradient-to-br from-lime-800
                                        to-lime-600 flex items-center justify-center mt-0.5 shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" />
                                </svg>
                            </div>
                            <div class="max-w-[75%] px-4 py-3 rounded-2xl rounded-bl-sm
                                        bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100
                                        text-sm leading-relaxed shadow-sm prose prose-sm dark:prose-invert max-w-none">
                                @if(isset($msg['typing']) && $msg['typing'] && $msg['content'] === '')
                                    <span class="flex items-center gap-1 py-0.5">
                                        <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                                        <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                                        <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce"></span>
                                    </span>
                                @else
                                    {!! \Illuminate\Support\Str::markdown(e($msg['content'])) !!}
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Error --}}
            @if($error)
                <div class="mx-6 mb-3 px-4 py-2.5 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200
                            dark:border-red-800 text-red-600 dark:text-red-400 text-sm flex-shrink-0">
                    ⚠️ {{ Str::limit($error, 120) }}
                </div>
            @endif

            {{-- Input area --}}
            <div class="flex-shrink-0 border-t border-slate-200 dark:border-slate-800 px-4 md:px-6 py-4
                        bg-white dark:bg-slate-950">
                <form
                    wire:submit.prevent="send"
                    class="flex items-end gap-3"
                    x-on:submit="resetTextarea()"
                >
                    <textarea
                        wire:model="message"
                        x-ref="chatInput"
                        x-on:keydown.enter.prevent="
                            if (!$event.shiftKey && $wire.message.trim()) {
                                $wire.send();
                                resetTextarea();
                            }
                        "
                        placeholder="Write message to Ben..."
                        rows="1"
                        style="height: 44px;"
                        class="flex-1 resize-none rounded-2xl border border-slate-200 dark:border-slate-700
                               bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white
                               placeholder-slate-400 dark:placeholder-slate-500 text-sm px-4 py-3
                               focus:outline-none focus:ring-2 focus:ring-lime-500/40 focus:border-lime-400
                               dark:focus:border-lime-500 transition-colors max-h-32 overflow-y-auto"
                        x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 128) + 'px';"
                        :disabled="$wire.thinking"
                    ></textarea>
                    <button
                        type="submit"
                        :disabled="$wire.thinking || !$wire.message.trim()"
                        class="flex-shrink-0 w-11 h-11 rounded-2xl flex items-center justify-center
                               bg-lime-600 hover:bg-lime-500 text-white
                               disabled:opacity-40 disabled:cursor-not-allowed
                               active:scale-95 transition-all shadow-sm shadow-lime-500/30"
                    >
                        @if($thinking)
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        @endif
                    </button>
                </form>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-2 text-center">
                    Enter para sugoon si Ben · Shift+Enter para bag-ong linya
                </p>
            </div>

        </div>{{-- end main --}}
    </div>
</div>