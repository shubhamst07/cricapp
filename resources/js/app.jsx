import React from 'react';
import { createRoot } from 'react-dom/client';
import Alpine from 'alpinejs';
import LiveScoresApp from './components/LiveScoresApp';
import MatchDetailApp from './components/MatchDetailApp';

// Start Alpine for Breeze navigation and standard dropdowns
window.Alpine = Alpine;
Alpine.start();

// Mount LiveScoresApp if container exists
const liveScoresRoot = document.getElementById('live-scores-root');
if (liveScoresRoot) {
    try {
        const live = JSON.parse(liveScoresRoot.getAttribute('data-live') || '[]');
        const scheduled = JSON.parse(liveScoresRoot.getAttribute('data-scheduled') || '[]');
        const completed = JSON.parse(liveScoresRoot.getAttribute('data-completed') || '[]');
        const cacheTtl = parseInt(liveScoresRoot.getAttribute('data-cache-ttl') || '15', 10);
        const initialTab = liveScoresRoot.getAttribute('data-initial-tab') || 'live';
        const initialSection = liveScoresRoot.getAttribute('data-initial-section') || 'international';

        createRoot(liveScoresRoot).render(
            <LiveScoresApp
                initialLive={live}
                initialScheduled={scheduled}
                initialCompleted={completed}
                cacheTtl={cacheTtl}
                initialTab={initialTab}
                initialSection={initialSection}
            />
        );
    } catch (e) {
        console.error('Error mounting LiveScoresApp:', e);
    }
}

// Mount MatchDetailApp if container exists
const matchDetailRoot = document.getElementById('match-detail-root');
if (matchDetailRoot) {
    try {
        const match = JSON.parse(matchDetailRoot.getAttribute('data-match') || '{}');
        const scorecard = JSON.parse(matchDetailRoot.getAttribute('data-scorecard') || '{}');
        const commentary = JSON.parse(matchDetailRoot.getAttribute('data-commentary') || '{}');
        const matchId = matchDetailRoot.getAttribute('data-match-id') || '';

        createRoot(matchDetailRoot).render(
            <MatchDetailApp
                initialMatch={match}
                initialScorecard={scorecard}
                initialCommentary={commentary}
                matchId={matchId}
            />
        );
    } catch (e) {
        console.error('Error mounting MatchDetailApp:', e);
    }
}
