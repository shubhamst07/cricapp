<div class="mb-2 p-3 rounded-xl bg-slate-900/90 border border-white/10 space-y-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="text-[11px] font-black uppercase tracking-wider text-amber-400">
            🔗 Add Internal SEO Link (1-Click)
        </span>
        <span class="text-[11px] text-slate-400">
            Format: <code class="text-emerald-400">[Link Text](/articles/slug)</code> or <code class="text-emerald-400">[Live Scores](/live-scores)</code>
        </span>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
        <select id="internal_link_target"
                class="sm:col-span-5 px-3 py-2 rounded-lg bg-slate-950 border border-white/10 text-xs text-white">
            <option value="/live-scores" data-label="Live Cricket Scores">🏏 Live Cricket Scores (/live-scores)</option>
            <option value="/articles" data-label="All Cricket News & Stories">📰 Cricket News &amp; Stories (/articles)</option>
            <option value="/matches" data-label="Local Tournament Scorecards">🏆 Local Matches (/matches)</option>
            <option value="/grounds" data-label="Cricket Grounds & Turf Booking">🏟️ Book Cricket Grounds (/grounds)</option>
            <option value="/umpires" data-label="Book Verified Cricket Umpires">👨‍⚖️ Verified Umpires (/umpires)</option>
            @foreach(($linkableArticles ?? collect()) as $linkArt)
                <option value="{{ route('articles.show', $linkArt->slug, false) }}" data-label="{{ $linkArt->title }}">
                    📄 {{ \Illuminate\Support\Str::limit($linkArt->title, 58) }}
                </option>
            @endforeach
        </select>
        <input type="text" id="internal_link_anchor"
               placeholder="Anchor text (e.g. India vs NZ Scorecard — optional)"
               class="sm:col-span-4 px-3 py-2 rounded-lg bg-slate-950 border border-white/10 text-xs text-white placeholder-slate-500" />
        <button type="button" onclick="insertCricAppInternalLink()"
                class="sm:col-span-3 px-3 py-2 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/30 text-emerald-300 text-xs font-black transition cursor-pointer">
            + Insert Link
        </button>
    </div>
</div>

<script>
    function insertCricAppInternalLink() {
        const targetSelect = document.getElementById('internal_link_target');
        const anchorInput = document.getElementById('internal_link_anchor');
        const textarea = document.getElementById('content');
        if (!targetSelect || !textarea) return;

        const selectedOpt = targetSelect.options[targetSelect.selectedIndex];
        const url = targetSelect.value || '/live-scores';
        const customAnchor = (anchorInput?.value || '').trim();
        const selectedStart = textarea.selectionStart || 0;
        const selectedEnd = textarea.selectionEnd || 0;
        const highlighted = textarea.value.slice(selectedStart, selectedEnd).trim();
        const label = customAnchor || highlighted || selectedOpt?.getAttribute('data-label') || 'Read more on CricApp.in';

        const mdLink = `[${label}](${url})`;
        const before = textarea.value.slice(0, selectedStart);
        const after = textarea.value.slice(selectedEnd);
        textarea.value = before + mdLink + after;
        textarea.focus();
        const cursorPos = before.length + mdLink.length;
        textarea.setSelectionRange(cursorPos, cursorPos);
        if (anchorInput) anchorInput.value = '';
    }
</script>
