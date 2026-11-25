import Alpine from 'alpinejs';

// Start Alpine immediately for navigation & Blade interactivity without waiting on React
window.Alpine = Alpine;
Alpine.start();

// Lazy-load React + LiveScoresApp only on pages containing #live-scores-root
const liveScoresRoot = document.getElementById('live-scores-root');
if (liveScoresRoot) {
    Promise.all([
        import('react'),
        import('react-dom/client'),
        import('./components/LiveScoresApp'),
    ]).then(([ReactMod, ReactDOMClient, { default: LiveScoresApp }]) => {
        const React = ReactMod.default || ReactMod;
        const { createRoot } = ReactDOMClient;
        const live = JSON.parse(liveScoresRoot.getAttribute('data-live') || '[]');
        const scheduled = JSON.parse(liveScoresRoot.getAttribute('data-scheduled') || '[]');
        const completed = JSON.parse(liveScoresRoot.getAttribute('data-completed') || '[]');
        const cacheTtl = parseInt(liveScoresRoot.getAttribute('data-cache-ttl') || '15', 10);
        const initialTab = liveScoresRoot.getAttribute('data-initial-tab') || 'live';
        const initialSection = liveScoresRoot.getAttribute('data-initial-section') || 'international';

        createRoot(liveScoresRoot).render(
            React.createElement(LiveScoresApp, {
                initialLive: live,
                initialScheduled: scheduled,
                initialCompleted: completed,
                cacheTtl,
                initialTab,
                initialSection,
            })
        );
    }).catch((e) => {
        console.error('Error mounting LiveScoresApp:', e);
    });
}

// Lazy-load React + MatchDetailApp only on pages containing #match-detail-root
const matchDetailRoot = document.getElementById('match-detail-root');
if (matchDetailRoot) {
    Promise.all([
        import('react'),
        import('react-dom/client'),
        import('./components/MatchDetailApp'),
    ]).then(([ReactMod, ReactDOMClient, { default: MatchDetailApp }]) => {
        const React = ReactMod.default || ReactMod;
        const { createRoot } = ReactDOMClient;
        const match = JSON.parse(matchDetailRoot.getAttribute('data-match') || '{}');
        const scorecard = JSON.parse(matchDetailRoot.getAttribute('data-scorecard') || '{}');
        const commentary = JSON.parse(matchDetailRoot.getAttribute('data-commentary') || '{}');
        const matchId = matchDetailRoot.getAttribute('data-match-id') || '';
        const isAdmin = matchDetailRoot.getAttribute('data-is-admin') === 'true';

        createRoot(matchDetailRoot).render(
            React.createElement(MatchDetailApp, {
                initialMatch: match,
                initialScorecard: scorecard,
                initialCommentary: commentary,
                matchId,
                initialIsAdmin: isAdmin,
            })
        );
    }).catch((e) => {
        console.error('Error mounting MatchDetailApp:', e);
    });
}
