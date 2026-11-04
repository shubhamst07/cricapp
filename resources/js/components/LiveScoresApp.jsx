import React, { useState, useEffect, useMemo } from 'react';
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
        return initialTab || (initialLive.length > 0 ? 'live' : (initialCompleted.length > 0 ? 'recent' : 'upcoming'));
    });

    const [liveMatches, setLiveMatches] = useState(initialLive);
    const [scheduledMatches, setScheduledMatches] = useState(initialScheduled);
    const [completedMatches, setCompletedMatches] = useState(initialCompleted);

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

    // Sync tab to URL without reload
    const handleTabChange = (tab) => {
        setActiveTab(tab);
        setSelectedSeries('all');
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    };

    // Manual or Auto-fetch data from API
    const fetchLatestData = async (forceRefresh = false) => {
        setIsRefreshing(true);
        try {
            const url = `/api/matches/summary${forceRefresh ? '?refresh=true' : ''}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('Failed to fetch matches');
            const data = await res.json();

            setLiveMatches(data.live || []);
            setScheduledMatches(data.scheduled || []);
            setCompletedMatches(data.completed || []);
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
            <div className="bg-white rounded-2xl shadow-xs border border-gray-100 p-6">
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <span className="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                                <Activity className="w-5 h-5" />
                            </span>
                            <div>
                                <h1 className="text-xl sm:text-2xl font-black text-gray-900 tracking-tight">
                                    Live Cricket Center
                                </h1>
                                <p className="text-xs text-gray-500 mt-0.5 flex items-center gap-1.5">
                                    <span>Real-time ball-by-ball scores & schedules</span>
                                    <span className="text-gray-300">•</span>
                                    <span>Updated at <strong className="text-gray-700">{lastUpdated}</strong></span>
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
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                    : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'
                            }`}
                        >
                            <span className={`w-2 h-2 rounded-full ${autoRefresh ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400'}`}></span>
                            Auto-refresh {autoRefresh ? 'ON' : 'OFF'}
                        </button>

                        <button
                            type="button"
                            onClick={() => fetchLatestData(true)}
                            disabled={isRefreshing}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition disabled:opacity-50"
                        >
                            <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
                            {isRefreshing ? 'Refreshing...' : 'Refresh'}
                        </button>
                    </div>
                </div>

                {/* Search & Format Filter Bar */}
                <div className="mt-6 pt-5 border-t border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    {/* Search Field */}
                    <div className="relative flex-1 max-w-md">
                        <Search className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            placeholder="Search team, tournament, player, or venue..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full pl-10 pr-9 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:bg-white transition"
                        />
                        {searchQuery && (
                            <button
                                onClick={() => setSearchQuery('')}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <X className="w-3.5 h-3.5" />
                            </button>
                        )}
                    </div>

                    {/* Format Filter Chips */}
                    <div className="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0">
                        <span className="text-xs font-bold text-gray-400 uppercase tracking-wider mr-1 flex items-center gap-1">
                            <Filter className="w-3 h-3" /> Format
                        </span>
                        {[
                            { id: 'all', label: 'All' },
                            { id: 't20', label: 'T20' },
                            { id: 'odi', label: 'ODI' },
                            { id: 'test', label: 'Test' }
                        ].map((f) => (
                            <button
                                key={f.id}
                                type="button"
                                onClick={() => setSelectedFormat(f.id)}
                                className={`px-3 py-1.5 rounded-lg text-xs font-bold transition ${
                                    selectedFormat === f.id
                                        ? 'bg-indigo-600 text-white shadow-xs'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                }`}
                            >
                                {f.label}
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            {/* Navigation Tabs (Live, Upcoming, Recent) */}
            <div className="bg-white rounded-2xl shadow-xs border border-gray-100 p-2">
                <nav className="flex space-x-2" aria-label="Tabs">
                    <button
                        type="button"
                        onClick={() => handleTabChange('live')}
                        className={`flex-1 flex items-center justify-center gap-2.5 py-3 px-4 rounded-xl text-xs sm:text-sm font-black transition ${
                            activeTab === 'live'
                                ? 'bg-rose-50 text-rose-700 shadow-xs'
                                : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50'
                        }`}
                    >
                        <span className="relative flex h-2.5 w-2.5">
                            {liveMatches.length > 0 && (
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            )}
                            <span className={`relative inline-flex rounded-full h-2.5 w-2.5 ${liveMatches.length > 0 ? 'bg-rose-600' : 'bg-gray-300'}`}></span>
                        </span>
                        <span>Live Matches</span>
                        <span className={`px-2 py-0.5 rounded-md text-xs font-black ${
                            activeTab === 'live' ? 'bg-rose-200/80 text-rose-900' : 'bg-gray-100 text-gray-600'
                        }`}>
                            {liveMatches.length}
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => handleTabChange('upcoming')}
                        className={`flex-1 flex items-center justify-center gap-2.5 py-3 px-4 rounded-xl text-xs sm:text-sm font-black transition ${
                            activeTab === 'upcoming'
                                ? 'bg-indigo-50 text-indigo-700 shadow-xs'
                                : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50'
                        }`}
                    >
                        <Calendar className="w-4 h-4" />
                        <span>Upcoming</span>
                        <span className={`px-2 py-0.5 rounded-md text-xs font-black ${
                            activeTab === 'upcoming' ? 'bg-indigo-200/80 text-indigo-900' : 'bg-gray-100 text-gray-600'
                        }`}>
                            {scheduledMatches.length}
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => handleTabChange('recent')}
                        className={`flex-1 flex items-center justify-center gap-2.5 py-3 px-4 rounded-xl text-xs sm:text-sm font-black transition ${
                            activeTab === 'recent'
                                ? 'bg-blue-50 text-blue-700 shadow-xs'
                                : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50'
                        }`}
                    >
                        <Trophy className="w-4 h-4" />
                        <span>Recent Results</span>
                        <span className={`px-2 py-0.5 rounded-md text-xs font-black ${
                            activeTab === 'recent' ? 'bg-blue-200/80 text-blue-900' : 'bg-gray-100 text-gray-600'
                        }`}>
                            {completedMatches.length}
                        </span>
                    </button>
                </nav>
            </div>

            {/* Series Filter & Selector Bar */}
            {seriesStats.length > 0 && (
                <div className="bg-white rounded-2xl shadow-xs border border-gray-100 p-4 space-y-3">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <div className="p-1.5 bg-amber-50 text-amber-700 rounded-lg">
                                <Trophy className="w-4 h-4" />
                            </div>
                            <span className="text-xs font-black uppercase text-gray-900 tracking-wider">
                                Series & Tournaments
                            </span>
                            <span className="text-xs text-gray-400 font-bold">
                                ({seriesStats.length} series in this section)
                            </span>
                        </div>

                        {selectedSeries !== 'all' && (
                            <button
                                type="button"
                                onClick={() => setSelectedSeries('all')}
                                className="inline-flex items-center gap-1.5 text-xs font-black text-indigo-600 hover:text-indigo-800 transition"
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
                            className={`shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-black transition ${
                                selectedSeries === 'all'
                                    ? 'bg-gray-900 text-white shadow-xs'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                            }`}
                        >
                            <span>All Series</span>
                            <span className={`px-1.5 py-0.5 rounded-md text-[10px] font-black ${
                                selectedSeries === 'all' ? 'bg-gray-700 text-gray-100' : 'bg-gray-200/80 text-gray-600'
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
                                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                            : 'bg-gray-50 text-gray-700 border-gray-200/80 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-200'
                                    }`}
                                >
                                    <Trophy className={`w-3.5 h-3.5 shrink-0 ${isSelected ? 'text-amber-300' : 'text-amber-500'}`} />
                                    <span className="truncate max-w-[220px]">{s.name}</span>
                                    <span className={`px-1.5 py-0.5 rounded-md text-[10px] font-black ${
                                        isSelected ? 'bg-indigo-700 text-indigo-100' : 'bg-gray-200/80 text-gray-600'
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
                <div className="bg-gradient-to-r from-indigo-50 via-white to-indigo-50 border border-indigo-200/80 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 bg-indigo-600 text-white rounded-xl shadow-xs">
                            <Trophy className="w-5 h-5 text-amber-300" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="text-[10px] font-black uppercase tracking-wider text-indigo-600">Showing Series</span>
                                <span className="px-2 py-0.5 bg-indigo-100 text-indigo-800 text-[10px] font-black rounded-md">
                                    {filteredMatches.length} {filteredMatches.length === 1 ? 'match' : 'matches'}
                                </span>
                            </div>
                            <h2 className="text-base font-black text-gray-900 mt-0.5">
                                {selectedSeries}
                            </h2>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={() => setSelectedSeries('all')}
                        className="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-white hover:bg-gray-50 text-gray-700 rounded-xl text-xs font-bold border border-gray-200 shadow-xs transition"
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
                                <div className="bg-white px-5 py-3 rounded-2xl border border-gray-200/80 shadow-xs flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2.5 min-w-0">
                                        <div className="p-1.5 bg-amber-50 text-amber-600 rounded-lg shrink-0">
                                            <Trophy className="w-4 h-4" />
                                        </div>
                                        <h3 className="font-black text-gray-900 text-sm truncate">
                                            {seriesName}
                                        </h3>
                                        <span className="px-2 py-0.5 rounded-md text-[11px] font-black bg-indigo-50 text-indigo-700 border border-indigo-100 shrink-0">
                                            {matches.length} {matches.length === 1 ? 'match' : 'matches'}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedSeries(seriesName)}
                                        className="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white rounded-xl text-xs font-bold transition shrink-0"
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
                <div className="bg-white rounded-2xl p-12 text-center shadow-xs border border-gray-100">
                    <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gray-50 text-gray-400 mb-3">
                        {activeTab === 'live' && <Radio className="w-7 h-7 text-rose-500 animate-pulse" />}
                        {activeTab === 'upcoming' && <Calendar className="w-7 h-7 text-indigo-500" />}
                        {activeTab === 'recent' && <Trophy className="w-7 h-7 text-blue-500" />}
                    </div>
                    <h3 className="text-base font-bold text-gray-900 mb-1">
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
                    <p className="text-xs text-gray-400 max-w-sm mx-auto mb-4">
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
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition"
                        >
                            <X className="w-3.5 h-3.5" />
                            Show All Series
                        </button>
                    )}
                </div>
            )}

            {/* Quick Scorecard Modal */}
            {modalMatch && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                        {/* Modal Header */}
                        <div className="p-5 border-b border-gray-100 flex items-center justify-between bg-gray-50/70">
                            <div>
                                <span className="text-[11px] font-black uppercase text-indigo-600 tracking-wider">
                                    {modalMatch.matchType || 'Match Preview'}
                                </span>
                                <h3 className="font-extrabold text-gray-900 text-base leading-tight mt-0.5">
                                    {modalMatch.name}
                                </h3>
                            </div>
                            <button
                                onClick={() => setModalMatch(null)}
                                className="p-1.5 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-200/50 transition"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Modal Body */}
                        <div className="p-6 overflow-y-auto space-y-4">
                            {modalMatch.score && modalMatch.score.length > 0 && (
                                <div className="bg-gray-50 border border-gray-100 rounded-xl p-4">
                                    <h4 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Scores</h4>
                                    <div className="space-y-2">
                                        {modalMatch.score.map((inn, idx) => {
                                            const innOvers = inn.overs ?? inn.o;
                                            const hasInnOvers = innOvers && innOvers !== '0' && innOvers !== 0 && innOvers !== '0.0';
                                            const innScore = inn.display_score || `${inn.runs ?? inn.r ?? 0}/${inn.wickets ?? inn.w ?? 0}`;
                                            return (
                                                <div key={idx} className="flex justify-between items-center text-sm py-1 border-b border-gray-200/50 last:border-0">
                                                    <span className="font-bold text-gray-800">{inn.batting_team || inn.inning || `Innings ${idx + 1}`}</span>
                                                    <span className="font-black text-gray-900">
                                                        {innScore}
                                                        {hasInnOvers && <span className="text-xs font-normal text-gray-500 ml-1.5">({innOvers} ov)</span>}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {modalMatch.status_text && (
                                <div className="p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs font-bold text-amber-900 flex items-center gap-2">
                                    <Shield className="w-4 h-4 text-amber-600 shrink-0" />
                                    <span>{modalMatch.status_text}</span>
                                </div>
                            )}

                            {scorecardData?.data?.player_of_the_match && (
                                <div className="p-3 bg-gradient-to-r from-amber-500/15 via-amber-400/10 to-yellow-500/5 border border-amber-300 rounded-xl flex items-center justify-between gap-3 shadow-xs">
                                    <div className="flex items-center gap-2.5 min-w-0">
                                        <div className="p-1.5 bg-amber-500 text-white rounded-lg shrink-0">
                                            <Trophy className="w-4 h-4" />
                                        </div>
                                        <div className="min-w-0">
                                            <span className="text-[10px] font-black uppercase text-amber-800 tracking-wider block">Player of the Match</span>
                                            <p className="text-xs font-extrabold text-gray-900 truncate">
                                                {scorecardData.data.player_of_the_match.name}
                                                {scorecardData.data.player_of_the_match.stats && (
                                                    <span className="font-semibold text-amber-900 ml-1">({scorecardData.data.player_of_the_match.stats})</span>
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="text-[10px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md shrink-0">
                                        Man of the Match
                                    </span>
                                </div>
                            )}

                            {loadingScorecard ? (
                                <div className="py-12 text-center text-gray-400">
                                    <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-indigo-500" />
                                    <p className="text-xs font-medium">Fetching real-time scorecard...</p>
                                </div>
                            ) : scorecardData && scorecardData.data ? (
                                <div className="space-y-4">
                                    {scorecardData.data.batting && scorecardData.data.batting.length > 0 && (
                                        <div className="border border-gray-100 rounded-xl overflow-hidden">
                                            <div className="bg-gray-50 px-4 py-2.5 text-xs font-bold text-gray-700">Batting</div>
                                            <table className="min-w-full divide-y divide-gray-200 text-xs">
                                                <thead className="bg-gray-50/50 font-semibold text-gray-600">
                                                    <tr>
                                                        <th className="px-4 py-2 text-left">Batter</th>
                                                        <th className="px-3 py-2 text-right">R</th>
                                                        <th className="px-3 py-2 text-right">B</th>
                                                        <th className="px-3 py-2 text-right">4s</th>
                                                        <th className="px-3 py-2 text-right">6s</th>
                                                        <th className="px-3 py-2 text-right">SR</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-100 bg-white">
                                                    {scorecardData.data.batting.map((player, pIdx) => (
                                                        <tr key={pIdx}>
                                                            <td className="px-4 py-2 font-bold text-gray-900">
                                                                {player.name}
                                                                <span className="block text-[10px] text-gray-400 font-normal">{player.out}</span>
                                                            </td>
                                                            <td className="px-3 py-2 text-right font-black text-gray-900">{player.runs ?? 0}</td>
                                                            <td className="px-3 py-2 text-right text-gray-500">{player.balls ?? 0}</td>
                                                            <td className="px-3 py-2 text-right text-gray-500">{player.fours ?? 0}</td>
                                                            <td className="px-3 py-2 text-right text-gray-500">{player.sixes ?? 0}</td>
                                                            <td className="px-3 py-2 text-right text-gray-700 font-semibold">{player.strikeRate ?? 0}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <p className="text-center text-xs text-gray-400 py-4">Scorecard details available in full Match Center.</p>
                            )}
                        </div>

                        {/* Modal Footer */}
                        <div className="p-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                            <a
                                href={`/live-scores/${modalMatch.id}`}
                                className="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition"
                            >
                                Open Full Match Center & Scorecard →
                            </a>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

// ==================== PREMIUM MATCH CARD COMPONENT ====================
function MatchCard({ match, type, onSelectSeries, onQuickScorecard }) {
    const isLive = type === 'live';
    const isUpcoming = type === 'upcoming';
    const isRecent = type === 'recent';

    // Format teams & scores cleanly
    const scores = match.score || [];
    const seriesTitle = match.series || match.series_name;

    return (
        <div className="bg-white rounded-2xl shadow-xs border border-gray-100 hover:shadow-md hover:border-gray-200 transition-all duration-200 flex flex-col justify-between overflow-hidden group">
            <div className="p-5">
                {/* Header Tag Row */}
                <div className="flex items-center justify-between mb-2.5">
                    <div className="flex items-center gap-1.5 flex-wrap">
                        {match.is_network_match && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300">
                                ⭐ My Network
                            </span>
                        )}
                        {match.is_local && !match.is_network_match && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-black bg-purple-50 text-purple-700 border border-purple-200">
                                🏏 Community
                            </span>
                        )}
                        {match.stream_url && (
                            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200">
                                🎥 Live Stream
                            </span>
                        )}
                        {isLive && (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-black bg-rose-50 text-rose-700 border border-rose-200">
                                <span className="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span>
                                LIVE
                            </span>
                        )}
                        {isUpcoming && (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-black bg-indigo-50 text-indigo-700 border border-indigo-200">
                                UPCOMING
                            </span>
                        )}
                        {isRecent && (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-black bg-blue-50 text-blue-700 border border-blue-200">
                                {match.status === 'abandoned' ? 'ABANDONED' : 'RESULT'}
                            </span>
                        )}
                        <span className="text-xs font-bold text-gray-500 uppercase tracking-wider truncate max-w-[140px]">
                            {match.matchType || 'Match'}
                        </span>
                    </div>

                    {/* Venue / Location */}
                    {match.venue && (
                        <span className="text-[11px] text-gray-400 font-medium truncate max-w-[120px]" title={match.venue}>
                            {match.venue.split(',')[0]}
                        </span>
                    )}
                </div>

                {/* Series Badge (Clickable) */}
                {seriesTitle && (
                    <div className="mb-2">
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                onSelectSeries && onSelectSeries(seriesTitle);
                            }}
                            className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50/80 hover:bg-amber-100/90 text-amber-900 border border-amber-200/80 transition max-w-full text-left truncate group/series"
                            title={`Click to view all matches in ${seriesTitle}`}
                        >
                            <Trophy className="w-3 h-3 text-amber-600 shrink-0" />
                            <span className="truncate group-hover/series:underline">{seriesTitle}</span>
                            <ChevronRight className="w-3 h-3 text-amber-500 shrink-0 opacity-70 group-hover/series:opacity-100" />
                        </button>
                    </div>
                )}

                {/* Match Title */}
                <h3 className="font-extrabold text-gray-900 text-sm leading-snug group-hover:text-indigo-600 transition line-clamp-1">
                    {match.name}
                </h3>

                {/* Scores List for Live & Recent */}
                {!isUpcoming && scores.length > 0 ? (
                    <div className="mt-4 space-y-2">
                        {scores.map((inning, idx) => {
                            const inningName = inning.batting_team || inning.inning || `Team ${idx + 1}`;
                            const runs = inning.runs ?? inning.r ?? 0;
                            const wickets = inning.wickets ?? inning.w ?? 0;
                            const overs = inning.overs ?? inning.o;
                            const hasOvers = overs && overs !== '0' && overs !== 0 && overs !== '0.0';
                            const displayScore = inning.display_score || `${runs}/${wickets}`;

                            return (
                                <div
                                    key={idx}
                                    className="flex items-center justify-between p-2.5 rounded-xl bg-gray-50 border border-gray-100"
                                >
                                    <div className="flex items-center gap-2">
                                        <div className="w-2 h-2 rounded-full bg-indigo-500"></div>
                                        <span className="text-xs font-bold text-gray-800 truncate max-w-[160px]">
                                            {inningName}
                                        </span>
                                    </div>
                                    <div className="text-right">
                                        <span className="text-sm font-black text-gray-900">
                                            {displayScore}
                                        </span>
                                        {hasOvers && (
                                            <span className="text-[11px] font-medium text-gray-500 ml-1.5">
                                                ({overs} ov)
                                            </span>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : isUpcoming && match.dateTimeGMT ? (
                    <div className="mt-4 p-3 bg-indigo-50/50 rounded-xl border border-indigo-100/80 flex items-center gap-2 text-xs text-indigo-900 font-semibold">
                        <Clock className="w-4 h-4 text-indigo-500 shrink-0" />
                        <span>Match Starts: {match.dateTimeGMT} GMT</span>
                    </div>
                ) : (
                    /* Default Teams View */
                    <div className="mt-4 p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs text-gray-500 font-medium">
                        {match.teams && match.teams.length > 0 ? match.teams.join(' vs ') : 'Match scheduled to begin soon'}
                    </div>
                )}

                {/* Status Text Banner */}
                {match.status_text && (
                    <div className={`mt-3 p-2.5 rounded-xl text-xs font-bold flex items-center gap-2 ${
                        isLive
                            ? 'bg-amber-50 text-amber-900 border border-amber-200/80'
                            : isRecent
                                ? 'bg-emerald-50 text-emerald-900 border border-emerald-200/80'
                                : 'bg-gray-50 text-gray-800 border border-gray-200'
                    }`}>
                        <span className="shrink-0">
                            {isLive ? <Flame className="w-3.5 h-3.5 text-amber-600" /> : <Shield className="w-3.5 h-3.5 text-emerald-600" />}
                        </span>
                        <span className="truncate">{match.status_text}</span>
                    </div>
                )}
            </div>

            {/* Card Action Footer */}
            <div className="px-5 py-3.5 bg-gray-50/70 border-t border-gray-100 flex items-center justify-between">
                <button
                    type="button"
                    onClick={onQuickScorecard}
                    className="text-xs font-bold text-gray-500 hover:text-gray-900 transition"
                >
                    Quick View
                </button>

                <a
                    href={match.is_local ? (match.match_url || `/matches/${match.local_id || match.id.replace('local_', '')}`) : `/live-scores/${match.id}`}
                    className="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white rounded-lg text-xs font-bold transition group-hover:bg-indigo-600 group-hover:text-white"
                >
                    <span>{match.is_local ? 'View Scorecard 📊' : 'Match Center'}</span>
                    <ChevronRight className="w-3.5 h-3.5" />
                </a>
            </div>
        </div>
    );
}
