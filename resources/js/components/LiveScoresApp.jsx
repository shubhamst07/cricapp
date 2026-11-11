import React, { useState, useEffect, useMemo } from 'react';
import AdSlot from './AdSlot';
import {
    Radio,
    Calendar,
    Trophy,
    Search,
    RefreshCw,
    MapPin,
    Clock,
    ChevronRight,
    X,
    Filter,
    Shield,
    Activity,
    Flame,
    Zap,
    ExternalLink
} from 'lucide-react';

export default function LiveScoresApp({
    initialLive = [],
    initialScheduled = [],
    initialCompleted = [],
    cacheTtl = 15,
    initialTab = 'live'
}) {
    const [activeTab, setActiveTab] = useState(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (['live', 'upcoming', 'recent'].includes(tabParam)) return tabParam;
        if (initialTab && ['live', 'upcoming', 'recent'].includes(initialTab)) return initialTab;
        try {
            const saved = localStorage.getItem('last_cricket_tab');
            if (['live', 'upcoming', 'recent'].includes(saved)) return saved;
        } catch (e) {}
        return (initialLive.length > 0 ? 'live' : (initialCompleted.length > 0 ? 'recent' : 'upcoming'));
    });

    const isMatchEnded = (m) => {
        if (!m) return false;
        if (m.status === 'completed' || m.status === 'abandoned' || m.matchEnded) return true;
        const st = (m.status_text || '').toLowerCase();
        if (['won by', 'won the', ' won', 'beat', 'tied', 'drawn', 'conceded', 'complete', 'result', 'defeated', 'match ended', 'abandoned'].some(w => st.includes(w))) return true;
        if (m.dateTimeGMT) {
            const matchTime = new Date(m.dateTimeGMT).getTime();
            if (!isNaN(matchTime) && Date.now() - matchTime > 12 * 3600 * 1000) {
                const isTest = (m.matchType || '').toLowerCase().includes('test') || st.includes('day ');
                if (!isTest) return true;
            }
        }
        if (Array.isArray(m.score) && m.score.length >= 2) {
            const o2 = parseFloat(m.score[1].overs || m.score[1].o || 0);
            if (o2 >= 50 || (o2 >= 20 && (m.matchType || '').toLowerCase().includes('t20'))) return true;
        }
        return false;
    };

    // Filter scheduled matches: move genuinely live matches to live, and completed matches to completed
    const wronglyScheduledLive = (initialScheduled || []).filter(m => m.score && Array.isArray(m.score) && m.score.length > 0 && !isMatchEnded(m));
    const wronglyScheduledCompleted = (initialScheduled || []).filter(m => isMatchEnded(m));
    const cleanScheduledInit = (initialScheduled || []).filter(m => !isMatchEnded(m) && !(m.score && Array.isArray(m.score) && m.score.length > 0));

    // Also sanitize initialLive: if any match in initialLive is already completed, move to completed
    const completedFromLive = (initialLive || []).filter(m => isMatchEnded(m));
    const cleanLiveInit = (initialLive || []).filter(m => !isMatchEnded(m));

    const sanitizedInitLive = [...cleanLiveInit, ...wronglyScheduledLive.map(m => ({ ...m, status: 'live' }))];
    const sanitizedInitCompleted = [...(initialCompleted || []), ...wronglyScheduledCompleted.map(m => ({ ...m, status: 'completed' })), ...completedFromLive.map(m => ({ ...m, status: 'completed' }))];

    const [liveMatches, setLiveMatches] = useState(sanitizedInitLive);
    const [scheduledMatches, setScheduledMatches] = useState(cleanScheduledInit);
    const [completedMatches, setCompletedMatches] = useState(sanitizedInitCompleted);

    const [searchQuery, setSearchQuery] = useState('');
    const [selectedFormat, setSelectedFormat] = useState('all');
    const [selectedSeries, setSelectedSeries] = useState('all');
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [lastUpdated, setLastUpdated] = useState(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }));

    // Modal state for quick scorecard
    const [modalMatch, setModalMatch] = useState(null);
    const [scorecardData, setScorecardData] = useState(null);
    const [loadingScorecard, setLoadingScorecard] = useState(false);

    // Sync browser Back/Forward navigation with tab
    useEffect(() => {
        const handlePopState = () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (['live', 'upcoming', 'recent'].includes(tabParam)) {
                setActiveTab(tabParam);
            }
        };
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    // Sync tab to URL and localStorage
    const handleTabChange = (tab) => {
        setActiveTab(tab);
        setSelectedSeries('all');
        try {
            localStorage.setItem('last_cricket_tab', tab);
        } catch (e) {}
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);
    };

    // Manual or Auto-fetch data from API
    const fetchLatestData = async (forceRefresh = false) => {
        setIsRefreshing(true);
        try {
            const url = `/api/matches/summary${forceRefresh ? '?refresh=true' : ''}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('Failed to fetch matches');
            const data = await res.json();

            let liveList = data.live || [];
            let scheduledList = data.scheduled || [];
            let completedList = data.completed || [];

            // Move any wrongly placed matches to their correct categories
            const completedFromLive = liveList.filter(m => isMatchEnded(m));
            const completedFromScheduled = scheduledList.filter(m => isMatchEnded(m));
            const liveFromScheduled = scheduledList.filter(m => m.score && Array.isArray(m.score) && m.score.length > 0 && !isMatchEnded(m));

            const cleanLive = liveList.filter(m => !isMatchEnded(m));
            const cleanScheduled = scheduledList.filter(m => !isMatchEnded(m) && !(m.score && Array.isArray(m.score) && m.score.length > 0));

            const finalLive = [...cleanLive, ...liveFromScheduled.map(m => ({ ...m, status: 'live' }))];
            const finalCompleted = [
                ...completedList,
                ...completedFromLive.map(m => ({ ...m, status: 'completed' })),
                ...completedFromScheduled.map(m => ({ ...m, status: 'completed' }))
            ];
            const seenComp = new Set();
            const dedupedCompleted = finalCompleted.filter(m => {
                if (!m || !m.id || seenComp.has(m.id)) return false;
                seenComp.add(m.id);
                return true;
            });

            setLiveMatches(finalLive);
            setScheduledMatches(cleanScheduled);
            setCompletedMatches(dedupedCompleted);
            setLastUpdated(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
        } catch (err) {
            console.error('Error fetching live matches:', err);
        } finally {
            setIsRefreshing(false);
        }
    };

    // Auto-refresh interval (every 20 seconds if enabled)
    useEffect(() => {
        if (!autoRefresh) return;
        const interval = setInterval(() => {
            fetchLatestData(false);
        }, 20000);
        return () => clearInterval(interval);
    }, [autoRefresh]);

    // Open scorecard modal and load data
    const openScorecardModal = async (match) => {
        setModalMatch(match);
        setScorecardData(null);
        setLoadingScorecard(true);
        try {
            const res = await fetch(`/api/matches/${match.id}/scorecard`, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                setScorecardData(data);
            }
        } catch (err) {
            console.error('Error loading scorecard:', err);
        } finally {
            setLoadingScorecard(false);
        }
    };

    // Current list based on active tab
    const currentList = useMemo(() => {
        if (activeTab === 'live') return liveMatches;
        if (activeTab === 'upcoming') return scheduledMatches;
        return completedMatches;
    }, [activeTab, liveMatches, scheduledMatches, completedMatches]);

    // Unique series for the active tab with match counts
    const seriesStats = useMemo(() => {
        const map = {};
        currentList.forEach(match => {
            const s = (match.series || match.series_name || 'Other Matches').trim();
            map[s] = (map[s] || 0) + 1;
        });
        return Object.entries(map)
            .map(([name, count]) => ({ name, count }))
            .sort((a, b) => b.count - a.count);
    }, [currentList]);

    // Filter matches by search query, format, and selected series
    const filteredMatches = useMemo(() => {
        return currentList.filter(match => {
            const nameMatch = (match.name || '').toLowerCase().includes(searchQuery.toLowerCase());
            const venueMatch = (match.venue || '').toLowerCase().includes(searchQuery.toLowerCase());
            const seriesMatch = (match.series || match.series_name || '').toLowerCase().includes(searchQuery.toLowerCase());
            const teamsMatch = Array.isArray(match.teams)
                ? match.teams.some(t => (t || '').toLowerCase().includes(searchQuery.toLowerCase()))
                : false;
            const playersMatch = Array.isArray(match.players)
                ? match.players.some(p => (p || '').toLowerCase().includes(searchQuery.toLowerCase()))
                : false;

            const matchesSearch = !searchQuery || nameMatch || venueMatch || seriesMatch || teamsMatch || playersMatch;
            if (!matchesSearch) return false;

            if (selectedFormat !== 'all') {
                const formatStr = (match.matchType || '').toLowerCase();
                if (!formatStr.includes(selectedFormat.toLowerCase())) return false;
            }

            if (selectedSeries !== 'all') {
                const matchSeries = (match.series || match.series_name || 'Other Matches').trim().toLowerCase();
                if (matchSeries !== selectedSeries.toLowerCase()) return false;
            }

            return true;
        });
    }, [currentList, searchQuery, selectedFormat, selectedSeries]);

    // Group filtered matches by series for series-wise presentation
    const matchesGroupedBySeries = useMemo(() => {
        const groups = {};
        filteredMatches.forEach(match => {
            const series = (match.series || match.series_name || 'Other Matches').trim();
            if (!groups[series]) {
                groups[series] = [];
            }
            groups[series].push(match);
        });
        return Object.entries(groups).map(([seriesName, matches]) => ({
            seriesName,
            matches
        }));
    }, [filteredMatches]);

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
            {/* Top Header Card */}
            <div className="bg-[#111827]/90 backdrop-blur-xl border border-white/10 rounded-3xl p-6 sm:p-7 shadow-2xl relative overflow-hidden">
                <div className="absolute -top-24 -right-24 w-60 h-60 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>

                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5 relative z-10">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-blue-600 text-white flex items-center justify-center shadow-lg shadow-indigo-600/30 shrink-0">
                                <Activity className="w-5 h-5" />
                            </span>
                            <div>
                                <h1 className="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2">
                                    <span>Live Cricket Center</span>
                                    <span className="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                                </h1>
                                <p className="text-xs text-slate-400 mt-0.5 flex flex-wrap items-center gap-2">
                                    <span>Real-time ball-by-ball international & domestic scores</span>
                                    <span className="text-slate-600">•</span>
                                    <span>Updated at <strong className="text-indigo-400 font-mono">{lastUpdated}</strong></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Actions & Auto-refresh */}
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setAutoRefresh(!autoRefresh)}
                            className={`inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold border transition ${
                                autoRefresh
                                    ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30 shadow-md shadow-emerald-500/10'
                                    : 'bg-white/5 text-slate-400 border-white/10 hover:bg-white/10 hover:text-white'
                            }`}
                        >
                            <span className={`w-2 h-2 rounded-full ${autoRefresh ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'}`}></span>
                            Auto-refresh {autoRefresh ? 'ON' : 'OFF'}
                        </button>

                        <button
                            type="button"
                            onClick={() => fetchLatestData(true)}
                            disabled={isRefreshing}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white rounded-xl text-xs font-black shadow-lg shadow-indigo-600/30 transition disabled:opacity-50 transform active:scale-95"
                        >
                            <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
                            {isRefreshing ? 'Refreshing...' : 'Refresh Score'}
                        </button>
                    </div>
                </div>

                {/* Search & Format Filter Bar */}
                <div className="mt-6 pt-5 border-t border-white/5 flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
                    {/* Search Field */}
                    <div className="relative flex-1 max-w-md">
                        <Search className="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            placeholder="Search team, tournament, player, or venue..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full pl-10 pr-9 py-2.5 text-xs bg-slate-950/80 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                        />
                        {searchQuery && (
                            <button
                                onClick={() => setSearchQuery('')}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                            >
                                <X className="w-3.5 h-3.5" />
                            </button>
                        )}
                    </div>

                    {/* Format Filter Chips */}
                    <div className="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0">
                        <span className="text-xs font-bold text-slate-400 uppercase tracking-wider mr-1 flex items-center gap-1">
                            <Filter className="w-3 h-3 text-indigo-400" /> Format
                        </span>
                        {[
                            { id: 'all', label: 'All Formats' },
                            { id: 't20', label: 'T20' },
                            { id: 'odi', label: 'ODI' },
                            { id: 'test', label: 'Test' }
                        ].map((f) => (
                            <button
                                key={f.id}
                                type="button"
                                onClick={() => setSelectedFormat(f.id)}
                                className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition border ${
                                    selectedFormat === f.id
                                        ? 'bg-gradient-to-r from-indigo-600 to-blue-600 text-white border-transparent shadow-md shadow-indigo-600/30'
                                        : 'bg-white/5 border-white/10 text-slate-300 hover:bg-white/10 hover:text-white'
                                }`}
                            >
                                {f.label}
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            {/* Navigation Tabs (Live, Upcoming, Recent) */}
            <div className="bg-[#111827]/80 backdrop-blur-md rounded-2xl shadow-xl border border-white/10 p-1.5">
                <nav className="flex space-x-2" aria-label="Tabs">
                    <button
                        type="button"
                        onClick={() => handleTabChange('live')}
                        className={`flex-1 flex items-center justify-center gap-2.5 py-3 px-4 rounded-xl text-xs sm:text-sm font-black transition ${
                            activeTab === 'live'
                                ? 'bg-gradient-to-r from-rose-600/25 to-red-600/20 text-rose-300 border border-rose-500/40 shadow-lg shadow-rose-600/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <span className="relative flex h-2.5 w-2.5">
                            {liveMatches.length > 0 && (
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            )}
                            <span className={`relative inline-flex rounded-full h-2.5 w-2.5 ${liveMatches.length > 0 ? 'bg-rose-500' : 'bg-slate-600'}`}></span>
                        </span>
                        <span>Live Matches</span>
                        <span className={`px-2 py-0.5 rounded-full text-xs font-black ${
                            activeTab === 'live' ? 'bg-rose-500 text-white' : 'bg-white/10 text-slate-400'
                        }`}>
                            {liveMatches.length}
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => handleTabChange('upcoming')}
                        className={`flex-1 flex items-center justify-center gap-2.5 py-3 px-4 rounded-xl text-xs sm:text-sm font-black transition ${
                            activeTab === 'upcoming'
                                ? 'bg-gradient-to-r from-indigo-600/25 to-blue-600/20 text-indigo-300 border border-indigo-500/40 shadow-lg shadow-indigo-600/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <Calendar className="w-4 h-4 text-indigo-400" />
                        <span>Upcoming</span>
                        <span className={`px-2 py-0.5 rounded-full text-xs font-black ${
                            activeTab === 'upcoming' ? 'bg-indigo-600 text-white' : 'bg-white/10 text-slate-400'
                        }`}>
                            {scheduledMatches.length}
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => handleTabChange('recent')}
                        className={`flex-1 flex items-center justify-center gap-2.5 py-3 px-4 rounded-xl text-xs sm:text-sm font-black transition ${
                            activeTab === 'recent'
                                ? 'bg-gradient-to-r from-blue-600/25 to-cyan-600/20 text-blue-300 border border-blue-500/40 shadow-lg shadow-blue-600/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <Trophy className="w-4 h-4 text-blue-400" />
                        <span>Recent Results</span>
                        <span className={`px-2 py-0.5 rounded-full text-xs font-black ${
                            activeTab === 'recent' ? 'bg-blue-600 text-white' : 'bg-white/10 text-slate-400'
                        }`}>
                            {completedMatches.length}
                        </span>
                    </button>
                </nav>
            </div>

            {/* Google AdSense / Sponsor Leaderboard Banner */}
            <AdSlot type="leaderboard" slotId="livescores-top-leaderboard" />

            {/* Series Filter & Selector Bar */}
            {seriesStats.length > 0 && (
                <div className="bg-[#111827]/80 rounded-2xl shadow-xl border border-white/10 p-4 space-y-3">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <div className="p-1.5 bg-amber-500/10 text-amber-400 rounded-lg border border-amber-500/20">
                                <Trophy className="w-4 h-4" />
                            </div>
                            <span className="text-xs font-black uppercase text-white tracking-wider">
                                Series & Tournaments
                            </span>
                            <span className="text-xs text-slate-400 font-bold">
                                ({seriesStats.length} series in this section)
                            </span>
                        </div>

                        {selectedSeries !== 'all' && (
                            <button
                                type="button"
                                onClick={() => setSelectedSeries('all')}
                                className="inline-flex items-center gap-1.5 text-xs font-black text-indigo-400 hover:text-indigo-300 transition"
                            >
                                <X className="w-3.5 h-3.5" />
                                Clear Series Filter (View All)
                            </button>
                        )}
                    </div>

                    {/* Quick Horizontal Series Pills */}
                    <div className="flex items-center gap-2 overflow-x-auto pb-1.5 pt-0.5 scrollbar-thin">
                        <button
                            type="button"
                            onClick={() => setSelectedSeries('all')}
                            className={`shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-black transition border ${
                                selectedSeries === 'all'
                                    ? 'bg-gradient-to-r from-indigo-600 to-blue-600 text-white border-transparent shadow-lg shadow-indigo-600/30'
                                    : 'bg-slate-950/70 border-white/10 text-slate-300 hover:bg-white/10 hover:text-white'
                            }`}
                        >
                            <span>All Series</span>
                            <span className={`px-1.5 py-0.5 rounded-md text-[10px] font-black ${
                                selectedSeries === 'all' ? 'bg-black/30 text-white' : 'bg-white/10 text-slate-400'
                            }`}>
                                {currentList.length}
                            </span>
                        </button>

                        {seriesStats.map((s) => {
                            const isSelected = selectedSeries.toLowerCase() === s.name.toLowerCase();
                            return (
                                <button
                                    key={s.name}
                                    type="button"
                                    onClick={() => setSelectedSeries(isSelected ? 'all' : s.name)}
                                    className={`shrink-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition border ${
                                        isSelected
                                            ? 'bg-gradient-to-r from-indigo-600 to-blue-600 text-white border-transparent shadow-lg shadow-indigo-600/30'
                                            : 'bg-slate-950/70 border-white/10 text-slate-300 hover:bg-indigo-600/10 hover:text-indigo-300 hover:border-indigo-500/30'
                                    }`}
                                >
                                    <Trophy className={`w-3.5 h-3.5 shrink-0 ${isSelected ? 'text-amber-300' : 'text-amber-400'}`} />
                                    <span className="truncate max-w-[220px]">{s.name}</span>
                                    <span className={`px-1.5 py-0.5 rounded-md text-[10px] font-black ${
                                        isSelected ? 'bg-black/30 text-white' : 'bg-white/10 text-slate-400'
                                    }`}>
                                        {s.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Active Selected Series Banner */}
            {selectedSeries !== 'all' && (
                <div className="bg-gradient-to-r from-indigo-950/60 via-slate-900 to-indigo-950/60 border border-indigo-500/30 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xl">
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 bg-gradient-to-tr from-indigo-600 to-blue-600 text-white rounded-xl shadow-lg shadow-indigo-600/30">
                            <Trophy className="w-5 h-5 text-amber-300" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="text-[10px] font-black uppercase tracking-wider text-indigo-400">Showing Series</span>
                                <span className="px-2 py-0.5 bg-indigo-500/20 text-indigo-300 text-[10px] font-black rounded-md border border-indigo-500/30">
                                    {filteredMatches.length} {filteredMatches.length === 1 ? 'match' : 'matches'}
                                </span>
                            </div>
                            <h2 className="text-base font-black text-white mt-0.5">
                                {selectedSeries}
                            </h2>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={() => setSelectedSeries('all')}
                        className="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold border border-white/10 transition"
                    >
                        <X className="w-4 h-4" />
                        Show All Series
                    </button>
                </div>
            )}

            {/* Matches Cards Grid */}
            {filteredMatches.length > 0 ? (
                selectedSeries === 'all' ? (
                    /* Grouped Series-wise View */
                    <div className="space-y-8">
                        {matchesGroupedBySeries.map(({ seriesName, matches }) => (
                            <div key={seriesName} className="space-y-4">
                                {/* Series Header */}
                                <div className="bg-[#111827]/80 px-5 py-3 rounded-2xl border border-white/10 shadow-xl flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2.5 min-w-0">
                                        <div className="p-1.5 bg-amber-500/10 text-amber-400 rounded-lg border border-amber-500/20 shrink-0">
                                            <Trophy className="w-4 h-4" />
                                        </div>
                                        <h3 className="font-black text-white text-sm truncate">
                                            {seriesName}
                                        </h3>
                                        <span className="px-2 py-0.5 rounded-md text-[11px] font-black bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 shrink-0">
                                            {matches.length} {matches.length === 1 ? 'match' : 'matches'}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedSeries(seriesName)}
                                        className="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-500/20 hover:bg-indigo-600 text-indigo-300 hover:text-white rounded-xl text-xs font-bold transition shrink-0"
                                    >
                                        <span>View Series</span>
                                        <ChevronRight className="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                {/* Match Cards in this Series */}
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                                    {matches.map((match) => (
                                        <MatchCard
                                            key={match.id}
                                            match={match}
                                            type={activeTab}
                                            onSelectSeries={setSelectedSeries}
                                            onQuickScorecard={() => openScorecardModal(match)}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    /* Filtered by Selected Series Grid */
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        {filteredMatches.map((match) => (
                            <MatchCard
                                key={match.id}
                                match={match}
                                type={activeTab}
                                onSelectSeries={setSelectedSeries}
                                onQuickScorecard={() => openScorecardModal(match)}
                            />
                        ))}
                    </div>
                )
            ) : (
                <div className="bg-[#111827]/80 rounded-3xl p-12 text-center shadow-xl border border-white/10">
                    <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/5 text-slate-400 mb-3 border border-white/5">
                        {activeTab === 'live' && <Radio className="w-7 h-7 text-rose-500 animate-pulse" />}
                        {activeTab === 'upcoming' && <Calendar className="w-7 h-7 text-indigo-400" />}
                        {activeTab === 'recent' && <Trophy className="w-7 h-7 text-blue-400" />}
                    </div>
                    <h3 className="text-base font-bold text-white mb-1">
                        {selectedSeries !== 'all'
                            ? `No matches found for ${selectedSeries}`
                            : searchQuery || selectedFormat !== 'all'
                                ? 'No matches match your filter criteria'
                                : activeTab === 'live'
                                    ? 'No live matches in progress right now'
                                    : activeTab === 'upcoming'
                                        ? 'No upcoming matches scheduled'
                                        : 'No recent match results available'}
                    </h3>
                    <p className="text-xs text-slate-400 max-w-sm mx-auto mb-4">
                        {selectedSeries !== 'all'
                            ? 'Try clearing the series filter to see all matches.'
                            : searchQuery
                                ? 'Try searching with another team or format keyword'
                                : 'Check back soon or hit refresh to see the latest schedules.'}
                    </p>
                    {selectedSeries !== 'all' && (
                        <button
                            type="button"
                            onClick={() => setSelectedSeries('all')}
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition"
                        >
                            <X className="w-3.5 h-3.5" />
                            Show All Series
                        </button>
                    )}
                </div>
            )}

            {/* Quick Scorecard Modal */}
            {modalMatch && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
                    <div className="bg-[#111827] border border-white/10 rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                        {/* Modal Header */}
                        <div className="p-5 border-b border-white/10 flex items-center justify-between bg-slate-950/70">
                            <div>
                                <span className="text-[11px] font-black uppercase text-indigo-400 tracking-wider">
                                    {modalMatch.matchType || 'Match Preview'}
                                </span>
                                <h3 className="font-black text-white text-base leading-tight mt-0.5 truncate">
                                    {modalMatch.name}
                                </h3>
                            </div>
                            <button
                                onClick={() => setModalMatch(null)}
                                className="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Modal Body */}
                        <div className="p-6 overflow-y-auto space-y-4">
                            {modalMatch.score && modalMatch.score.length > 0 && (
                                <div className="bg-slate-950/80 border border-white/5 rounded-2xl p-4">
                                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Scores</h4>
                                    <div className="space-y-2">
                                        {modalMatch.score.map((inn, idx) => {
                                            const innOvers = inn.overs ?? inn.o;
                                            const hasInnOvers = innOvers && innOvers !== '0' && innOvers !== 0 && innOvers !== '0.0';
                                            const innScore = inn.display_score || `${inn.runs ?? inn.r ?? 0}/${inn.wickets ?? inn.w ?? 0}`;
                                            return (
                                                <div key={idx} className="flex justify-between items-center text-sm py-1.5 border-b border-white/5 last:border-0">
                                                    <span className="font-bold text-slate-200">{inn.batting_team || inn.inning || `Innings ${idx + 1}`}</span>
                                                    <span className="font-black text-white font-mono">
                                                        {innScore}
                                                        {hasInnOvers && <span className="text-xs font-bold text-sky-400 ml-1.5 font-mono">({innOvers} ov)</span>}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {modalMatch.status_text && (
                                <div className="p-3.5 bg-amber-500/15 border border-amber-500/30 rounded-2xl text-xs font-bold text-amber-300 flex items-center gap-2">
                                    <Shield className="w-4 h-4 text-amber-400 shrink-0" />
                                    <span>{modalMatch.status_text}</span>
                                </div>
                            )}

                            {scorecardData?.data?.player_of_the_match && (
                                <div className="p-3 bg-gradient-to-r from-amber-500/20 via-amber-400/10 to-yellow-500/5 border border-amber-500/30 rounded-2xl flex items-center justify-between gap-3 shadow-xs">
                                    <div className="flex items-center gap-2.5 min-w-0">
                                        <div className="p-1.5 bg-amber-500 text-slate-950 rounded-lg shrink-0">
                                            <Trophy className="w-4 h-4" />
                                        </div>
                                        <div className="min-w-0">
                                            <span className="text-[10px] font-black uppercase text-amber-400 tracking-wider block">Player of the Match</span>
                                            <p className="text-xs font-black text-white truncate">
                                                {scorecardData.data.player_of_the_match.name}
                                                {scorecardData.data.player_of_the_match.stats && (
                                                    <span className="font-semibold text-amber-300 ml-1">({scorecardData.data.player_of_the_match.stats})</span>
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="text-[10px] font-bold text-amber-300 bg-amber-500/20 border border-amber-500/30 px-2 py-0.5 rounded-md shrink-0">
                                        Man of the Match
                                    </span>
                                </div>
                            )}

                            {loadingScorecard ? (
                                <div className="py-12 text-center text-slate-400">
                                    <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-indigo-400" />
                                    <p className="text-xs font-medium">Fetching real-time scorecard...</p>
                                </div>
                            ) : scorecardData && scorecardData.data ? (
                                <div className="space-y-4">
                                    {scorecardData.data.batting && scorecardData.data.batting.length > 0 && (
                                        <div className="border border-white/5 rounded-2xl overflow-hidden bg-slate-950/60">
                                            <div className="bg-white/5 px-4 py-2.5 text-xs font-bold text-slate-300">Batting</div>
                                            <table className="min-w-full divide-y divide-white/5 text-xs">
                                                <thead className="bg-white/[0.02] font-semibold text-slate-400">
                                                    <tr>
                                                        <th className="px-4 py-2 text-left">Batter</th>
                                                        <th className="px-3 py-2 text-right">R</th>
                                                        <th className="px-3 py-2 text-right">B</th>
                                                        <th className="px-3 py-2 text-right">4s</th>
                                                        <th className="px-3 py-2 text-right">6s</th>
                                                        <th className="px-3 py-2 text-right">SR</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-white/5">
                                                    {scorecardData.data.batting.map((player, pIdx) => (
                                                        <tr key={pIdx}>
                                                            <td className="px-4 py-2 font-bold text-white">
                                                                {player.name}
                                                                <span className="block text-[10px] text-slate-400 font-normal">{player.out}</span>
                                                            </td>
                                                            <td className="px-3 py-2 text-right font-black text-white font-mono">{player.runs ?? 0}</td>
                                                            <td className="px-3 py-2 text-right text-slate-400 font-mono">{player.balls ?? 0}</td>
                                                            <td className="px-3 py-2 text-right text-slate-400 font-mono">{player.fours ?? 0}</td>
                                                            <td className="px-3 py-2 text-right text-slate-400 font-mono">{player.sixes ?? 0}</td>
                                                            <td className="px-3 py-2 text-right text-sky-400 font-semibold font-mono">{player.strikeRate ?? 0}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <p className="text-center text-xs text-slate-400 py-4">Scorecard details available in full Match Center.</p>
                            )}
                        </div>

                        {/* Modal Footer */}
                        <div className="p-4 bg-slate-950/80 border-t border-white/10 flex justify-end">
                            <a
                                href={`/live-scores/${modalMatch.id}?tab=${activeTab || 'live'}`}
                                className="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white rounded-xl text-xs font-black shadow-lg shadow-indigo-600/30 transition"
                            >
                                Open Full Match Center & Scorecard →
                            </a>
                        </div>
                    </div>
                </div>
            )}

            {/* Mobile Sticky Bottom Sponsor Banner */}
            <AdSlot type="sticky-bottom" slotId="livescores-sticky" />
        </div>
    );
}

// ==================== PREMIUM MATCH CARD COMPONENT ====================
function MatchCard({ match, type, onSelectSeries, onQuickScorecard }) {
    const hasLiveScore = Array.isArray(match.score) && match.score.length > 0;
    const isLive = type === 'live' || (type === 'upcoming' && hasLiveScore);
    const isUpcoming = type === 'upcoming' && !hasLiveScore;
    const isRecent = type === 'recent';

    // Format teams & scores cleanly
    const scores = match.score || [];
    const seriesTitle = match.series || match.series_name;

    return (
        <div className={`rounded-3xl border transition-all duration-300 flex flex-col justify-between overflow-hidden group relative bg-[#131b2e] ${
            isLive
                ? 'border-rose-500/30 hover:border-rose-500/60 shadow-xl hover:shadow-2xl hover:shadow-rose-500/10'
                : 'border-white/10 hover:border-indigo-500/50 shadow-xl hover:shadow-2xl hover:shadow-indigo-500/10'
        }`}>
            {/* Top Indicator Strip for Live Matches */}
            {isLive && (
                <div className="h-1 w-full bg-gradient-to-r from-rose-500 via-red-500 to-amber-500"></div>
            )}

            <div className="p-5 space-y-3.5">
                {/* Header Tag Row */}
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-1.5 flex-wrap">
                        {match.is_network_match && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                ⭐ My Network
                            </span>
                        )}
                        {match.is_local && !match.is_network_match && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-black bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                🏏 Community
                            </span>
                        )}
                        {match.stream_url && (
                            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-black bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                🎥 Live Stream
                            </span>
                        )}
                        {isLive && (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-black bg-rose-500/20 text-rose-300 border border-rose-500/40">
                                <span className="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                LIVE
                            </span>
                        )}
                        {isUpcoming && (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-black bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">
                                UPCOMING
                            </span>
                        )}
                        {isRecent && (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-black bg-blue-500/20 text-blue-300 border border-blue-500/40">
                                {match.status === 'abandoned' ? 'ABANDONED' : 'RESULT'}
                            </span>
                        )}
                        <span className="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-slate-300 truncate max-w-[140px]">
                            {match.matchType || 'Match'}
                        </span>
                    </div>

                    {/* Venue / Location */}
                    {match.venue && (
                        <span className="text-[11px] text-slate-400 font-medium truncate max-w-[120px] flex items-center gap-1 shrink-0" title={match.venue}>
                            <MapPin className="w-3 h-3 text-slate-500" />
                            {match.venue.split(',')[0]}
                        </span>
                    )}
                </div>

                {/* Series Badge (Clickable) */}
                {seriesTitle && (
                    <div>
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                onSelectSeries && onSelectSeries(seriesTitle);
                            }}
                            className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/20 transition max-w-full text-left truncate group/series"
                            title={`Click to view all matches in ${seriesTitle}`}
                        >
                            <Trophy className="w-3 h-3 text-amber-400 shrink-0" />
                            <span className="truncate group-hover/series:underline">{seriesTitle}</span>
                            <ChevronRight className="w-3 h-3 text-amber-400 shrink-0 opacity-70 group-hover/series:opacity-100" />
                        </button>
                    </div>
                )}

                {/* Match Title */}
                <h3 className="font-black text-white text-base leading-snug group-hover:text-indigo-400 transition line-clamp-1">
                    {match.name}
                </h3>

                {/* Scores List for Live & Recent */}
                {!isUpcoming && scores.length > 0 ? (
                    <div className="space-y-2 pt-1">
                        {scores.map((inning, idx) => {
                            const inningName = inning.batting_team || inning.inning || `Team ${idx + 1}`;
                            const runs = inning.runs ?? inning.r ?? 0;
                            let wickets = inning.wickets ?? inning.w ?? 0;
                            const overs = inning.overs ?? inning.o;
                            const hasOvers = overs && overs !== '0' && overs !== 0 && overs !== '0.0';

                            // Correction: If match is completed, and team lost by runs or stopped chasing, wickets must be 10 (all out), not 0
                            const isMatchFinished = isRecent || (match.matchEnded ?? false) || ['won by', ' won', 'beat', 'tied', 'drawn', 'complete', 'result'].some(w => (match.status_text || '').toLowerCase().includes(w));
                            if (isMatchFinished && idx === 1 && wickets === 0) {
                                const st = (match.status_text || '').toLowerCase();
                                if (st.includes('won by') && st.includes('run')) {
                                    wickets = 10;
                                } else if (hasOvers && parseFloat(overs) < 50 && (match.matchType || '').toLowerCase().includes('odi')) {
                                    wickets = 10;
                                }
                            }

                            let displayScore = inning.display_score;
                            if (!displayScore || (displayScore === `${runs}/0` && (wickets > 0 || isMatchFinished))) {
                                displayScore = wickets === 10 ? `${runs}/10` : `${runs}/${wickets}`;
                            }

                            return (
                                <div
                                    key={idx}
                                    className="flex items-center justify-between p-3 rounded-2xl bg-slate-950/80 border border-white/5"
                                >
                                    <div className="flex items-center gap-2.5 min-w-0">
                                        <div className={`w-2 h-2 rounded-full shrink-0 ${idx === 0 ? 'bg-indigo-400' : 'bg-emerald-400'}`}></div>
                                        <span className="text-xs font-bold text-slate-200 truncate max-w-[160px]">
                                            {inningName}
                                        </span>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <span className="text-base font-black text-white font-mono">
                                            {displayScore}
                                        </span>
                                        {hasOvers && (
                                            <span className="text-xs font-bold text-sky-400 ml-1.5 font-mono">
                                                ({overs} ov)
                                            </span>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : isUpcoming && match.dateTimeGMT ? (
                    <div className="p-3 bg-indigo-500/10 rounded-2xl border border-indigo-500/20 flex items-center gap-2 text-xs text-indigo-300 font-semibold">
                        <Clock className="w-4 h-4 text-indigo-400 shrink-0" />
                        <span>Match Starts: {match.dateTimeGMT} GMT</span>
                    </div>
                ) : (
                    /* Default Teams View */
                    <div className="p-3 bg-slate-950/60 rounded-2xl border border-white/5 text-xs text-slate-400 font-medium">
                        {match.teams && match.teams.length > 0 ? match.teams.join(' vs ') : 'Match scheduled to begin soon'}
                    </div>
                )}

                {/* Status Text Banner */}
                {match.status_text && (
                    <div className={`p-2.5 rounded-xl text-xs font-bold flex items-center gap-2 ${
                        isLive
                            ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30'
                            : isRecent
                                ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30'
                                : 'bg-slate-950/80 text-slate-300 border border-white/10'
                    }`}>
                        <span className="shrink-0">
                            {isLive ? <Flame className="w-3.5 h-3.5 text-amber-400" /> : <Shield className="w-3.5 h-3.5 text-emerald-400" />}
                        </span>
                        <span className="truncate">{match.status_text}</span>
                    </div>
                )}
            </div>

            {/* Card Action Footer */}
            <div className="px-5 py-3.5 bg-slate-950/70 border-t border-white/5 flex items-center justify-between">
                <button
                    type="button"
                    onClick={onQuickScorecard}
                    className="text-xs font-bold text-slate-400 hover:text-white transition"
                >
                    Quick View
                </button>

                <a
                    href={match.is_local ? (match.match_url || `/matches/${match.local_id || match.id.replace('local_', '')}`) : `/live-scores/${match.id}?tab=${isLive ? 'live' : (type || 'live')}`}
                    className="inline-flex items-center gap-1 px-4 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white rounded-xl text-xs font-extrabold shadow-lg shadow-indigo-600/30 transition transform active:scale-95"
                >
                    <span>{match.is_local ? 'View Scorecard 📊' : 'Match Center'}</span>
                    <ChevronRight className="w-3.5 h-3.5" />
                </a>
            </div>
        </div>
    );
}
