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
    ExternalLink,
    Globe,
    Archive,
    FolderArchive,
    AlertCircle,
    User,
    Sparkles,
    TrendingUp,
    Award,
    Star
} from 'lucide-react';

export default function LiveScoresApp({
    initialLive = [],
    initialScheduled = [],
    initialCompleted = [],
    cacheTtl = 15,
    initialTab = 'live',
    initialSection = 'international'
}) {
    useEffect(() => {
        document.title = 'LiveCricket - Real-Time Cricket Scores & Updates';
    }, []);

    // Current primary section: 'international' or 'local'
    const [currentSection, setCurrentSection] = useState(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const secParam = urlParams.get('section');
        if (['international', 'local'].includes(secParam)) return secParam;
        if (['international', 'local'].includes(initialSection)) return initialSection;
        try {
            const savedSec = localStorage.getItem('last_cricket_section');
            if (['international', 'local'].includes(savedSec)) return savedSec;
        } catch (e) {}
        return 'international';
    });

    const [activeTab, setActiveTab] = useState(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (['live', 'upcoming', 'recent', 'archives'].includes(tabParam)) return tabParam;
        if (initialTab && ['live', 'upcoming', 'recent', 'archives'].includes(initialTab)) return initialTab;
        try {
            const saved = localStorage.getItem('last_cricket_tab');
            if (['live', 'upcoming', 'recent', 'archives'].includes(saved)) return saved;
        } catch (e) {}
        return (initialLive.length > 0 ? 'live' : (initialCompleted.length > 0 ? 'recent' : 'upcoming'));
    });

    // Helper: is match from local platform
    const isLocalMatch = (m) => Boolean(m && (m.is_local || String(m.id || '').startsWith('local_')));

    // Helper: check if completed match is from past 2 days (48 hours)
    const isPastTwoDays = (m) => {
        if (!m) return false;
        const dateVal = m.dateTimeGMT || m.match_date;
        if (!dateVal) return true; // fallback if no date
        const t = new Date(dateVal).getTime();
        if (isNaN(t)) return true;
        // Past 3 days (72 hours) or dismissed match -> show in Recent Results
        const diffHours = (Date.now() - t) / (3600 * 1000);
        return diffHours <= 72;
    };

    const isMatchEnded = (m) => {
        if (!m) return false;
        const st = (m.status_text || '').toLowerCase();
        // Local platform matches: status is set by umpire / admin or auto-dismissal
        if (isLocalMatch(m)) {
            return m.status === 'completed' || m.status === 'abandoned' || Boolean(m.is_dismissed) || st.includes('dismiss');
        }

        // 1. Any match with explicit completed text (won by, beat, tied, drawn, etc.) is ALWAYS ENDED!
        const isCompletedText = ['won by', 'won the match', 'won the series', ' beat ', 'tied', 'drawn', 'conceded', 'match ended', 'abandon', 'cancel', 'no result'].some(w => st.includes(w));
        if (isCompletedText) {
            return true;
        }

        // 2. INNINGS BREAK / BREAK / LUNCH / TEA / DINNER: ALWAYS LIVE, NEVER ENDED!
        const isInningsBreak = ['innings break', 'inn break', 'break', 'lunch', 'tea', 'dinner', 'strategic timeout', 'drinks'].some(w => st.includes(w));
        if (isInningsBreak) {
            return false;
        }

        // 3. Chasing matches are ALWAYS LIVE, NEVER ended!
        const isChasing = ['need ', 'require ', 'target '].some(w => st.includes(w));
        if (isChasing) return false;

        // 4. Real toss is LIVE/upcoming, not ended
        const isToss = ['opt to', 'elected to', 'chose to'].some(w => st.includes(w)) || (st.includes('toss') && !st.includes('abandon'));
        if (isToss) return false;

        // 5. Ongoing Test match (Stumps, trail by, lead by, Day 1-4) is NOT ended!
        if ((st.includes('stump') || st.includes('trail') || st.includes('lead') || /day\s*[1-4]/.test(st)) &&
            !['won by', 'won the match', ' beat ', 'drawn', 'tied', 'abandon'].some(w => st.includes(w))) {
            return false;
        }

        // 6. In-Progress, Rain, Weather Delays
        const isInProgress = st.includes('in progress') || st.includes('rain') || st.includes('delay');
        if (isInProgress) return false;

        if (m.status === 'completed' || m.status === 'abandoned' || m.matchEnded) {
            return true;
        }

        // Scheduled matches with no scores are definitely UPCOMING, never ended!
        if (m.status === 'scheduled' && (!m.score || !Array.isArray(m.score) || m.score.length === 0)) {
            return false;
        }

        if (Array.isArray(m.score) && m.score.length >= 2) {
            const isTest = (m.matchType || '').toLowerCase().includes('test') || /day\s*[1-4]/.test(st);
            if (!isTest) {
                const o2 = parseFloat(m.score[1].overs || m.score[1].o || 0);
                if (o2 >= 50 || (o2 >= 20 && (m.matchType || '').toLowerCase().includes('t20'))) return true;
            }
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

    // Player Search & Career Profile Modal state
    const [playerResults, setPlayerResults] = useState([]);
    const [isSearchingPlayers, setIsSearchingPlayers] = useState(false);
    const [selectedPlayer, setSelectedPlayer] = useState(null);
    const [playerProfileData, setPlayerProfileData] = useState(null);
    const [loadingPlayerProfile, setLoadingPlayerProfile] = useState(false);

    // Sync browser Back/Forward navigation with section and tab
    useEffect(() => {
        const handlePopState = () => {
            const urlParams = new URLSearchParams(window.location.search);
            const secParam = urlParams.get('section');
            if (['international', 'local'].includes(secParam)) {
                setCurrentSection(secParam);
            }
            const tabParam = urlParams.get('tab');
            if (['live', 'upcoming', 'recent', 'archives'].includes(tabParam)) {
                setActiveTab(tabParam);
            }
        };
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    // Sync section change
    const handleSectionChange = (section) => {
        setCurrentSection(section);
        setSelectedSeries('all');
        try {
            localStorage.setItem('last_cricket_section', section);
        } catch (e) {}
        const url = new URL(window.location);
        url.searchParams.set('section', section);
        window.history.pushState({}, '', url);
    };

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

    // Live Player Search Debounce
    useEffect(() => {
        const q = searchQuery.trim();
        if (q.length < 2) {
            setPlayerResults([]);
            return;
        }

        const timer = setTimeout(async () => {
            try {
                setIsSearchingPlayers(true);
                const res = await fetch(`/api/players/lookup?query=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.status === 'success' && Array.isArray(data.players)) {
                        setPlayerResults(data.players);
                    } else {
                        setPlayerResults([]);
                    }
                }
            } catch (err) {
                console.error('Player search error:', err);
            } finally {
                setIsSearchingPlayers(false);
            }
        }, 220);

        return () => clearTimeout(timer);
    }, [searchQuery]);

    // Open player career profile modal
    const handleOpenPlayerProfile = async (player) => {
        setSelectedPlayer(player);
        setPlayerProfileData(null);
        setLoadingPlayerProfile(true);
        try {
            const pId = player.id || player.user_id;
            const res = await fetch(`/api/player/${pId}?name=${encodeURIComponent(player.name)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.status === 'success' && json.data) {
                    setPlayerProfileData(json.data);
                }
            }
        } catch (err) {
            console.error('Error fetching player profile:', err);
        } finally {
            setLoadingPlayerProfile(false);
        }
    };

    // Helper to find matches for a given player or country
    const getMatchesForPlayer = (player) => {
        if (!player) return [];
        const pCountry = (player.country || '').toLowerCase().trim();
        const pName = (player.name || '').toLowerCase().trim();
        const allPool = [...liveMatches, ...scheduledMatches, ...completedMatches];
        return allPool.filter(m => {
            const nameStr = (m.name || '').toLowerCase();
            const seriesStr = (m.series || m.series_name || '').toLowerCase();
            const teamsArr = Array.isArray(m.teams) ? m.teams.map(t => (t || '').toLowerCase()) : [];
            if (pName && (nameStr.includes(pName) || seriesStr.includes(pName))) return true;
            if (pCountry && pCountry !== 'local club' && pCountry !== 'grassroots cricket') {
                if (nameStr.includes(pCountry) || seriesStr.includes(pCountry) || teamsArr.includes(pCountry)) return true;
            }
            return false;
        });
    };

    // Filter by Section (International vs Local)
    const sectionLive = useMemo(() => {
        return liveMatches.filter(m => currentSection === 'local' ? isLocalMatch(m) : !isLocalMatch(m));
    }, [liveMatches, currentSection]);

    const sectionScheduled = useMemo(() => {
        return scheduledMatches.filter(m => currentSection === 'local' ? isLocalMatch(m) : !isLocalMatch(m));
    }, [scheduledMatches, currentSection]);

    // Section completed split into Recent (past 2 days) and Archives (older / dismissed)
    const { sectionRecent, sectionArchives } = useMemo(() => {
        const pool = completedMatches.filter(m => currentSection === 'local' ? isLocalMatch(m) : !isLocalMatch(m));
        const recent = [];
        const archives = [];
        pool.forEach(m => {
            if (isPastTwoDays(m)) {
                recent.push(m);
            } else {
                archives.push(m);
            }
        });
        return { sectionRecent: recent, sectionArchives: archives };
    }, [completedMatches, currentSection]);

    // Overall Section Counts for the top switcher
    const internationalCounts = useMemo(() => {
        const l = liveMatches.filter(m => !isLocalMatch(m)).length;
        const u = scheduledMatches.filter(m => !isLocalMatch(m)).length;
        const c = completedMatches.filter(m => !isLocalMatch(m));
        const r = c.filter(m => isPastTwoDays(m)).length;
        const a = c.filter(m => !isPastTwoDays(m)).length;
        return { live: l, upcoming: u, recent: r, archives: a, total: l + u + r + a };
    }, [liveMatches, scheduledMatches, completedMatches]);

    const localCounts = useMemo(() => {
        const l = liveMatches.filter(m => isLocalMatch(m)).length;
        const u = scheduledMatches.filter(m => isLocalMatch(m)).length;
        const c = completedMatches.filter(m => isLocalMatch(m));
        const r = c.filter(m => isPastTwoDays(m)).length;
        const a = c.filter(m => !isPastTwoDays(m)).length;
        return { live: l, upcoming: u, recent: r, archives: a, total: l + u + r + a };
    }, [liveMatches, scheduledMatches, completedMatches]);

    // Current list based on active tab
    const currentList = useMemo(() => {
        if (activeTab === 'live') return sectionLive;
        if (activeTab === 'upcoming') return sectionScheduled;
        if (activeTab === 'archives') return sectionArchives;
        return sectionRecent;
    }, [activeTab, sectionLive, sectionScheduled, sectionRecent, sectionArchives]);

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

    // Filter matches by search query, format, and selected series (supports player search e.g. Rohit -> India match)
    const filteredMatches = useMemo(() => {
        const q = searchQuery.toLowerCase().trim();
        if (!q) return currentList;

        // Player to team mapping dictionary
        const playerTeamMap = {
            'rohit': 'india', 'sharma': 'india', 'hitman': 'india',
            'virat': 'india', 'kohli': 'india', 'cheeku': 'india',
            'bumrah': 'india', 'jassi': 'india',
            'dhoni': 'india', 'msd': 'india', 'thala': 'india',
            'gill': 'india', 'shubman': 'india',
            'pant': 'india', 'rishabh': 'india',
            'hardik': 'india', 'pandya': 'india',
            'jadeja': 'india', 'jaddu': 'india',
            'shami': 'india', 'siraj': 'india',
            'kl rahul': 'india', 'rahul': 'india',
            'surya': 'india', 'sky': 'india', 'suryakumar': 'india',
            'jaiswal': 'india', 'ashwin': 'india', 'kuldeep': 'india',
            'axar': 'india', 'arshdeep': 'india', 'samson': 'india', 'ishan': 'india',
            'babar': 'pakistan', 'rizwan': 'pakistan', 'shaheen': 'pakistan', 'naseem': 'pakistan',
            'cummins': 'australia', 'head': 'australia', 'starc': 'australia', 'smith': 'australia', 'warner': 'australia', 'maxwell': 'australia',
            'root': 'england', 'stokes': 'england', 'buttler': 'england', 'brook': 'england',
            'williamson': 'new zealand', 'boult': 'new zealand', 'ravindra': 'new zealand',
            'rashid': 'afghanistan', 'gurbaz': 'afghanistan',
            'rabada': 'south africa', 'klaasen': 'south africa', 'de kock': 'south africa',
        };

        const playerCountries = playerResults.map(p => (p.country || '').toLowerCase().trim()).filter(c => c && c !== 'local club' && c !== 'grassroots cricket');
        const mappedTeam = playerTeamMap[q] || (playerCountries.length > 0 ? playerCountries[0] : null);

        return currentList.filter(match => {
            const nameMatch = (match.name || '').toLowerCase().includes(q);
            const venueMatch = (match.venue || '').toLowerCase().includes(q);
            const seriesMatch = (match.series || match.series_name || '').toLowerCase().includes(q);
            const statusMatch = (match.status_text || '').toLowerCase().includes(q);
            const teamsMatch = Array.isArray(match.teams)
                ? match.teams.some(t => (t || '').toLowerCase().includes(q))
                : false;
            const playersMatch = Array.isArray(match.players)
                ? match.players.some(p => (p || '').toLowerCase().includes(q))
                : false;
            const batsmenMatch = Array.isArray(match.current_batsmen)
                ? match.current_batsmen.some(b => (b?.name || '').toLowerCase().includes(q))
                : false;

            // Match if this player's national team is in this match
            const teamPlayerMatch = mappedTeam && (
                (match.name || '').toLowerCase().includes(mappedTeam) ||
                (match.series || match.series_name || '').toLowerCase().includes(mappedTeam) ||
                (Array.isArray(match.teams) && match.teams.some(t => (t || '').toLowerCase().includes(mappedTeam)))
            );

            const matchesSearch = nameMatch || venueMatch || seriesMatch || statusMatch || teamsMatch || playersMatch || batsmenMatch || teamPlayerMatch;
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
    }, [currentList, searchQuery, selectedFormat, selectedSeries, playerResults]);

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
                            placeholder="Search team (e.g. India), venue, or player (e.g. Virat Kohli)..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full pl-10 pr-12 py-2.5 text-xs bg-slate-950/80 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                        />
                        <div className="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5">
                            {isSearchingPlayers && (
                                <RefreshCw className="w-3.5 h-3.5 text-indigo-400 animate-spin" />
                            )}
                            {searchQuery && (
                                <button
                                    onClick={() => {
                                        setSearchQuery('');
                                        setPlayerResults([]);
                                    }}
                                    className="text-slate-400 hover:text-white transition p-0.5"
                                    title="Clear search"
                                >
                                    <X className="w-3.5 h-3.5" />
                                </button>
                            )}
                        </div>
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

                {/* Cricketers Found Bar (Quick Profile Preview chips) */}
                {playerResults.length > 0 && (
                    <div className="mt-4 pt-3.5 border-t border-white/5 animate-in fade-in duration-200">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[11px] font-black uppercase tracking-wider text-indigo-400 flex items-center gap-1.5">
                                <Sparkles className="w-3.5 h-3.5 text-amber-400" /> Matching Cricketers ({playerResults.length})
                            </span>
                            <span className="text-[10px] text-slate-400">Click any cricketer to open career profile & records</span>
                        </div>
                        <div className="flex items-center gap-2.5 overflow-x-auto pb-1.5 scrollbar-thin">
                            {playerResults.map((player) => {
                                const pMatches = getMatchesForPlayer(player);
                                const hasLiveMatch = pMatches.some(m => m.status === 'live' || (Array.isArray(m.score) && m.score.length > 0));
                                return (
                                    <button
                                        key={player.id}
                                        type="button"
                                        onClick={() => handleOpenPlayerProfile(player)}
                                        className="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-slate-950/90 border border-white/10 hover:border-indigo-500/60 hover:bg-slate-900 transition-all shrink-0 text-left group shadow-md"
                                    >
                                        <img
                                            src={player.avatar}
                                            alt={player.name}
                                            className="w-8 h-8 rounded-lg object-cover border border-white/10 shrink-0 group-hover:scale-105 transition"
                                            onError={(e) => {
                                                e.target.onerror = null;
                                                e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(player.name)}&background=0D8ABC&color=fff`;
                                            }}
                                        />
                                        <div className="min-w-0 pr-1">
                                            <div className="flex items-center gap-1.5">
                                                <span className="text-xs font-black text-white group-hover:text-indigo-400 transition truncate">
                                                    {player.name}
                                                </span>
                                                <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-white/10 text-slate-300">
                                                    {player.country}
                                                </span>
                                                {hasLiveMatch && (
                                                    <span className="text-[9px] font-black px-1.5 py-0.2 rounded bg-rose-500/20 text-rose-300 border border-rose-500/40 flex items-center gap-1 shrink-0">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                                        Match Live
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-[10px] text-slate-400 truncate">
                                                {player.role}
                                            </p>
                                        </div>
                                        <ChevronRight className="w-3.5 h-3.5 text-slate-500 group-hover:text-indigo-400 transition shrink-0 ml-1" />
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>

            {/* Active Section Banner Header (Seamless with Top Navbar) */}
            <div className="flex flex-wrap items-center justify-between gap-3 px-1 py-1">
                <div className="flex items-center gap-3">
                    {currentSection === 'local' ? (
                        <div className="inline-flex items-center gap-2.5 px-4 py-2 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-black text-sm shadow-lg shadow-emerald-500/5">
                            <Shield className="w-4 h-4 text-emerald-400" />
                            <span>🏏 Local Cricket Arena</span>
                            <span className="px-2.5 py-0.5 rounded-full text-xs bg-emerald-500/20 text-emerald-300">
                                {localCounts.total} Matches
                            </span>
                        </div>
                    ) : (
                        <div className="inline-flex items-center gap-2.5 px-4 py-2 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-black text-sm shadow-lg shadow-indigo-500/5">
                            <Globe className="w-4 h-4 text-indigo-400" />
                            <span>🌍 International Cricket</span>
                            <span className="px-2.5 py-0.5 rounded-full text-xs bg-indigo-500/20 text-indigo-300">
                                {internationalCounts.total} Matches
                            </span>
                        </div>
                    )}
                </div>
                <div className="text-xs text-slate-400 font-medium hidden sm:block">
                    {currentSection === 'local'
                        ? 'Grassroots, Turf & Club Cricket'
                        : 'Worldwide ICC & Domestic Matches • Real-Time Ball-by-Ball'}
                </div>
            </div>

            {/* Sub-Navigation Tabs (Live, Upcoming, Recent Results, Archives) */}
            <div className="bg-[#111827]/80 backdrop-blur-md rounded-2xl shadow-xl border border-white/10 p-1.5">
                <nav className="grid grid-cols-2 md:grid-cols-4 gap-2" aria-label="Tabs">
                    {/* Live Matches */}
                    <button
                        type="button"
                        onClick={() => handleTabChange('live')}
                        className={`flex items-center justify-center gap-2 py-3 px-3 rounded-xl text-xs sm:text-sm font-black transition ${
                            activeTab === 'live'
                                ? 'bg-gradient-to-r from-rose-600/25 to-red-600/20 text-rose-300 border border-rose-500/40 shadow-lg shadow-rose-600/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <span className="relative flex h-2.5 w-2.5">
                            {sectionLive.length > 0 && (
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            )}
                            <span className={`relative inline-flex rounded-full h-2.5 w-2.5 ${sectionLive.length > 0 ? 'bg-rose-500' : 'bg-slate-600'}`}></span>
                        </span>
                        <span>Live Matches</span>
                        <span className={`px-2 py-0.5 rounded-full text-xs font-black ${
                            activeTab === 'live' ? 'bg-rose-500 text-white' : 'bg-white/10 text-slate-400'
                        }`}>
                            {sectionLive.length}
                        </span>
                    </button>

                    {/* Upcoming Matches */}
                    <button
                        type="button"
                        onClick={() => handleTabChange('upcoming')}
                        className={`flex items-center justify-center gap-2 py-3 px-3 rounded-xl text-xs sm:text-sm font-black transition ${
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
                            {sectionScheduled.length}
                        </span>
                    </button>

                    {/* Recent Results (Past 2 Days) */}
                    <button
                        type="button"
                        onClick={() => handleTabChange('recent')}
                        className={`flex items-center justify-center gap-2 py-3 px-3 rounded-xl text-xs sm:text-sm font-black transition ${
                            activeTab === 'recent'
                                ? 'bg-gradient-to-r from-blue-600/25 to-cyan-600/20 text-blue-300 border border-blue-500/40 shadow-lg shadow-blue-600/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <Trophy className="w-4 h-4 text-blue-400" />
                        <div className="flex flex-col items-center">
                            <span>Recent Results</span>
                            <span className="text-[10px] font-medium text-blue-300/80 -mt-0.5">(Past 2 Days)</span>
                        </div>
                        <span className={`px-2 py-0.5 rounded-full text-xs font-black ${
                            activeTab === 'recent' ? 'bg-blue-600 text-white' : 'bg-white/10 text-slate-400'
                        }`}>
                            {sectionRecent.length}
                        </span>
                    </button>

                    {/* Archives (Series-wise) */}
                    <button
                        type="button"
                        onClick={() => handleTabChange('archives')}
                        className={`flex items-center justify-center gap-2 py-3 px-3 rounded-xl text-xs sm:text-sm font-black transition ${
                            activeTab === 'archives'
                                ? 'bg-gradient-to-r from-amber-600/25 to-yellow-600/20 text-amber-300 border border-amber-500/40 shadow-lg shadow-amber-600/20'
                                : 'text-slate-400 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <Archive className="w-4 h-4 text-amber-400" />
                        <div className="flex flex-col items-center">
                            <span>Archives</span>
                            <span className="text-[10px] font-medium text-amber-300/80 -mt-0.5">(Series-wise)</span>
                        </div>
                        <span className={`px-2 py-0.5 rounded-full text-xs font-black ${
                            activeTab === 'archives' ? 'bg-amber-600 text-white' : 'bg-white/10 text-slate-400'
                        }`}>
                            {sectionArchives.length}
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
                                            highlightedPlayer={playerResults[0] || null}
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
                                highlightedPlayer={playerResults[0] || null}
                            />
                        ))}
                    </div>
                )
            ) : playerResults.length > 0 ? (
                /* Player Search Results Showcase when match list is empty */
                <div className="bg-[#111827]/90 rounded-3xl p-6 sm:p-8 shadow-xl border border-indigo-500/30 text-left animate-in fade-in duration-200">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-white/10">
                        <div className="flex items-center gap-3">
                            <div className="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
                                <User className="w-6 h-6" />
                            </div>
                            <div>
                                <span className="text-[10px] font-black uppercase text-indigo-400 tracking-wider flex items-center gap-1.5">
                                    <Sparkles className="w-3.5 h-3.5 text-amber-400" /> Cricketer Career Search
                                </span>
                                <h3 className="text-lg font-black text-white">
                                    {playerResults.length} Player{playerResults.length > 1 ? 's' : ''} found matching "{searchQuery}"
                                </h3>
                            </div>
                        </div>
                        <span className="text-xs text-slate-400">
                            Click any player card to view career statistics & records
                        </span>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                        {playerResults.map((player) => {
                            const pMatches = getMatchesForPlayer(player);
                            return (
                                <div
                                    key={player.id}
                                    onClick={() => handleOpenPlayerProfile(player)}
                                    className="p-4 rounded-2xl bg-slate-950/70 border border-white/10 hover:border-indigo-500/60 hover:bg-slate-900/90 transition-all cursor-pointer group flex items-center justify-between gap-4 shadow-lg hover:shadow-indigo-500/10"
                                >
                                    <div className="flex items-center gap-3.5 min-w-0">
                                        <img
                                            src={player.avatar}
                                            alt={player.name}
                                            className="w-14 h-14 rounded-2xl object-cover border border-white/10 bg-slate-900 shrink-0 group-hover:scale-105 transition"
                                            onError={(e) => {
                                                e.target.onerror = null;
                                                e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(player.name)}&background=0D8ABC&color=fff`;
                                            }}
                                        />
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 mb-0.5">
                                                <h4 className="text-sm font-black text-white group-hover:text-indigo-400 transition truncate">
                                                    {player.name}
                                                </h4>
                                                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/10 text-slate-300">
                                                    {player.country}
                                                </span>
                                            </div>
                                            <p className="text-xs text-slate-400 truncate">
                                                {player.role} • {player.batting_style}
                                            </p>
                                            {pMatches.length > 0 ? (
                                                <div className="mt-1 flex items-center gap-1.5 text-[11px] text-emerald-400 font-bold truncate">
                                                    <Radio className="w-3 h-3 text-rose-500 animate-pulse shrink-0" />
                                                    <span className="truncate">{pMatches[0].name} ({pMatches[0].status_text || pMatches[0].status})</span>
                                                </div>
                                            ) : player.highlights && (
                                                <p className="text-[11px] text-amber-400 font-semibold truncate mt-1">
                                                    ★ {player.highlights}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        className="px-3.5 py-2 rounded-xl bg-indigo-600/20 group-hover:bg-indigo-600 text-indigo-300 group-hover:text-white border border-indigo-500/30 text-xs font-bold transition shrink-0 flex items-center gap-1 shadow-sm"
                                    >
                                        <span>Profile</span>
                                        <ChevronRight className="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>
            ) : (
                <div className="bg-[#111827]/80 rounded-3xl p-12 text-center shadow-xl border border-white/10">
                    <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/5 text-slate-400 mb-3 border border-white/5">
                        {activeTab === 'live' && <Radio className="w-7 h-7 text-rose-500 animate-pulse" />}
                        {activeTab === 'upcoming' && <Calendar className="w-7 h-7 text-indigo-400" />}
                        {activeTab === 'recent' && <Trophy className="w-7 h-7 text-blue-400" />}
                        {activeTab === 'archives' && <Archive className="w-7 h-7 text-amber-400" />}
                    </div>
                    <h3 className="text-base font-bold text-white mb-1">
                        {selectedSeries !== 'all'
                            ? `No matches found for ${selectedSeries}`
                            : searchQuery || selectedFormat !== 'all'
                                ? 'No matches match your filter criteria'
                                : activeTab === 'live'
                                    ? (currentSection === 'local' ? 'No live local matches in progress right now' : 'No live international matches in progress right now')
                                    : activeTab === 'upcoming'
                                        ? (currentSection === 'local' ? 'No upcoming local matches scheduled' : 'No upcoming international matches scheduled')
                                        : activeTab === 'archives'
                                            ? (currentSection === 'local' ? 'No archived local tournament matches' : 'No archived international series matches')
                                            : (currentSection === 'local' ? 'No recent local match results in the past 2 days' : 'No recent international results in the past 2 days')}
                    </h3>
                    <p className="text-xs text-slate-400 max-w-sm mx-auto mb-4">
                        {selectedSeries !== 'all'
                            ? 'Try clearing the series filter to see all matches.'
                            : searchQuery
                                ? 'Try searching with another team or format keyword'
                                : activeTab === 'recent'
                                    ? 'Older tournament matches are stored safely in the Archives tab.'
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

            {/* Player Career Profile Modal */}
            {selectedPlayer && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
                    <div className="bg-[#111827] border border-white/10 rounded-3xl shadow-2xl max-w-3xl w-full max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                        {/* Modal Header */}
                        <div className="p-5 border-b border-white/10 flex items-center justify-between bg-slate-950/80">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-2xl bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
                                    <User className="w-5 h-5" />
                                </div>
                                <div>
                                    <span className="text-[10px] font-black uppercase tracking-wider text-indigo-400 flex items-center gap-1.5">
                                        <Sparkles className="w-3 h-3 text-amber-400" /> Cricketer Career Profile
                                    </span>
                                    <h3 className="font-black text-white text-base leading-tight mt-0.5">
                                        {selectedPlayer.name}
                                    </h3>
                                </div>
                            </div>
                            <button
                                onClick={() => {
                                    setSelectedPlayer(null);
                                    setPlayerProfileData(null);
                                }}
                                className="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-xl transition"
                                title="Close profile"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Modal Body */}
                        <div className="p-5 sm:p-6 overflow-y-auto space-y-6 flex-1">
                            {loadingPlayerProfile ? (
                                <div className="py-20 text-center space-y-3">
                                    <RefreshCw className="w-8 h-8 text-indigo-400 animate-spin mx-auto" />
                                    <p className="text-xs font-bold text-slate-400">Loading {selectedPlayer.name}'s records & statistics...</p>
                                </div>
                            ) : playerProfileData ? (
                                <>
                                    {/* Player Hero Card */}
                                    <div className="p-5 rounded-2xl bg-gradient-to-br from-indigo-950/40 via-slate-900 to-slate-950 border border-white/10 flex flex-col sm:flex-row items-center sm:items-start gap-5">
                                        <div className="relative shrink-0">
                                            <img
                                                src={playerProfileData.image || selectedPlayer.avatar}
                                                alt={playerProfileData.name}
                                                className="w-24 h-24 rounded-2xl object-cover border-2 border-indigo-500/40 shadow-xl bg-slate-950"
                                                onError={(e) => {
                                                    e.target.onerror = null;
                                                    e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(playerProfileData.name)}&background=0D8ABC&color=fff&size=200`;
                                                }}
                                            />
                                            <span className="absolute -bottom-2 -right-2 px-2 py-0.5 text-[9px] font-black bg-indigo-600 text-white rounded-md shadow-md uppercase tracking-wider">
                                                {playerProfileData.personal_info?.role || selectedPlayer.role}
                                            </span>
                                        </div>

                                        <div className="flex-1 text-center sm:text-left min-w-0">
                                            <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-1.5">
                                                <h2 className="text-xl font-black text-white">{playerProfileData.name}</h2>
                                                <span className="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-white/10 text-slate-300 border border-white/10">
                                                    {selectedPlayer.country}
                                                </span>
                                            </div>

                                            {playerProfileData.highlights && (
                                                <p className="text-xs text-amber-300 font-bold mb-3 flex items-center justify-center sm:justify-start gap-1.5">
                                                    <Award className="w-3.5 h-3.5 text-amber-400 shrink-0" />
                                                    <span>{playerProfileData.highlights}</span>
                                                </p>
                                            )}

                                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5 pt-2 border-t border-white/5 text-[11px]">
                                                <div>
                                                    <span className="text-slate-500 block text-[10px] uppercase font-bold">Batting Style</span>
                                                    <span className="text-slate-200 font-semibold">{playerProfileData.personal_info?.batting_style || 'N/A'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-500 block text-[10px] uppercase font-bold">Bowling Style</span>
                                                    <span className="text-slate-200 font-semibold">{playerProfileData.personal_info?.bowling_style || 'N/A'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-500 block text-[10px] uppercase font-bold">Born / Age</span>
                                                    <span className="text-slate-200 font-semibold">{playerProfileData.personal_info?.born || 'N/A'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-slate-500 block text-[10px] uppercase font-bold">Birth Place</span>
                                                    <span className="text-slate-200 font-semibold">{playerProfileData.personal_info?.birth_place || 'N/A'}</span>
                                                </div>
                                                <div className="col-span-2 sm:col-span-2">
                                                    <span className="text-slate-500 block text-[10px] uppercase font-bold">Teams</span>
                                                    <span className="text-indigo-300 font-semibold truncate block">{playerProfileData.personal_info?.teams || 'N/A'}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Bio summary */}
                                    {playerProfileData.bio && (
                                        <div className="p-3.5 rounded-2xl bg-white/5 border border-white/5 text-xs text-slate-300 leading-relaxed">
                                            {playerProfileData.bio}
                                        </div>
                                    )}

                                    {/* Active & Scheduled Matches for this Player */}
                                    {(() => {
                                        const pMatches = (playerProfileData.active_matches && playerProfileData.active_matches.length > 0)
                                            ? playerProfileData.active_matches
                                            : getMatchesForPlayer(selectedPlayer);
                                        if (pMatches.length === 0) return null;
                                        return (
                                            <div className="space-y-3 p-4 rounded-2xl bg-indigo-950/40 border border-indigo-500/30">
                                                <div className="flex items-center justify-between">
                                                    <h4 className="text-xs font-black text-white uppercase tracking-wider flex items-center gap-1.5">
                                                        <Radio className="w-3.5 h-3.5 text-rose-500 animate-pulse" /> Active & Scheduled Matches ({pMatches.length})
                                                    </h4>
                                                    <span className="text-[10px] text-indigo-300 font-bold">Matches featuring {selectedPlayer.country}</span>
                                                </div>
                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    {pMatches.map((pm) => {
                                                        const isLive = pm.status === 'live' || (Array.isArray(pm.score) && pm.score.length > 0);
                                                        const scores = pm.score || [];
                                                        return (
                                                            <div
                                                                key={pm.id}
                                                                className="p-3.5 rounded-xl bg-slate-950/80 border border-white/10 hover:border-indigo-500/50 transition flex flex-col justify-between gap-3 shadow-md"
                                                            >
                                                                <div>
                                                                    <div className="flex items-center justify-between gap-2 mb-1.5">
                                                                        <span className="text-[9px] font-black uppercase px-2 py-0.5 rounded bg-white/10 text-indigo-300">
                                                                            {pm.matchType || 'Match'}
                                                                        </span>
                                                                        <span className={`text-[9px] font-black px-2 py-0.5 rounded-full ${
                                                                            isLive ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30 animate-pulse' : 'bg-white/10 text-slate-400'
                                                                        }`}>
                                                                            {pm.status_text || pm.status || 'Scheduled'}
                                                                        </span>
                                                                    </div>
                                                                    <h5 className="text-xs font-black text-white line-clamp-1">{pm.name}</h5>
                                                                    <p className="text-[10px] text-slate-400 line-clamp-1 mt-0.5">{pm.series || pm.venue}</p>

                                                                    {scores.length > 0 && (
                                                                        <div className="mt-2 pt-2 border-t border-white/5 space-y-1">
                                                                            {scores.map((sc, sIdx) => (
                                                                                <div key={sIdx} className="flex justify-between items-center text-[11px]">
                                                                                    <span className="text-slate-300 font-medium truncate">{sc.batting_team || sc.inning || `Team ${sIdx + 1}`}</span>
                                                                                    <span className="font-black text-white font-mono">{sc.display_score || `${sc.runs ?? sc.r ?? 0}/${sc.wickets ?? sc.w ?? 0}`}</span>
                                                                                </div>
                                                                            ))}
                                                                        </div>
                                                                    )}
                                                                </div>

                                                                <a
                                                                    href={`/live-scores/${pm.id}?tab=live`}
                                                                    className="w-full py-2 px-3 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white rounded-xl text-center text-xs font-bold transition flex items-center justify-center gap-1 shadow-md shadow-indigo-600/20"
                                                                >
                                                                    <span>Open Match Center & Live Score →</span>
                                                                </a>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        );
                                    })()}

                                    {/* Batting Career Records Table */}
                                    {playerProfileData.batting_career?.stats?.length > 0 && (
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <h4 className="text-xs font-black text-white uppercase tracking-wider flex items-center gap-1.5">
                                                    <TrendingUp className="w-3.5 h-3.5 text-indigo-400" /> Batting Career Records
                                                </h4>
                                            </div>
                                            <div className="overflow-x-auto rounded-2xl border border-white/10 bg-slate-950/60">
                                                <table className="w-full text-left text-xs whitespace-nowrap">
                                                    <thead>
                                                        <tr className="border-b border-white/10 bg-white/5 text-slate-400 font-black text-[10px] uppercase">
                                                            <th className="px-3 py-2.5">Format</th>
                                                            <th className="px-3 py-2.5 text-right">Mat</th>
                                                            <th className="px-3 py-2.5 text-right">Inns</th>
                                                            <th className="px-3 py-2.5 text-right font-black text-indigo-400">Runs</th>
                                                            <th className="px-3 py-2.5 text-right">HS</th>
                                                            <th className="px-3 py-2.5 text-right text-emerald-400">Avg</th>
                                                            <th className="px-3 py-2.5 text-right text-sky-400">SR</th>
                                                            <th className="px-3 py-2.5 text-right text-amber-400">100s</th>
                                                            <th className="px-3 py-2.5 text-right text-blue-300">50s</th>
                                                            <th className="px-3 py-2.5 text-right">4s</th>
                                                            <th className="px-3 py-2.5 text-right">6s</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-white/5 font-medium">
                                                        {playerProfileData.batting_career.stats.map((row, idx) => {
                                                            const fmt = row.format || row.label || `Format ${idx + 1}`;
                                                            const mat = row.matches ?? (row.values ? row.values[0] : '-');
                                                            const inns = row.innings ?? (row.values ? row.values[1] : '-');
                                                            const runs = row.runs ?? (row.values ? row.values[2] : 0);
                                                            const hs = row.highest_score ?? (row.values ? row.values[3] : '-');
                                                            const avg = row.average ?? (row.values ? row.values[4] : '-');
                                                            const sr = row.strike_rate ?? (row.values ? row.values[5] : '-');
                                                            const hundreds = row.hundreds ?? (row.values ? row.values[6] : 0);
                                                            const fifties = row.fifties ?? (row.values ? row.values[7] : 0);
                                                            const fours = row.fours ?? (row.values ? row.values[8] : '-');
                                                            const sixes = row.sixes ?? (row.values ? row.values[9] : '-');
                                                            return (
                                                                <tr key={idx} className="hover:bg-white/5 transition">
                                                                    <td className="px-3 py-2 font-black text-white">{fmt}</td>
                                                                    <td className="px-3 py-2 text-right text-slate-300 font-mono">{mat}</td>
                                                                    <td className="px-3 py-2 text-right text-slate-400 font-mono">{inns}</td>
                                                                    <td className="px-3 py-2 text-right font-black text-white font-mono">{runs}</td>
                                                                    <td className="px-3 py-2 text-right text-slate-300 font-mono">{hs}</td>
                                                                    <td className="px-3 py-2 text-right font-bold text-emerald-400 font-mono">{avg}</td>
                                                                    <td className="px-3 py-2 text-right font-bold text-sky-400 font-mono">{sr}</td>
                                                                    <td className="px-3 py-2 text-right font-bold text-amber-400 font-mono">{hundreds}</td>
                                                                    <td className="px-3 py-2 text-right font-bold text-blue-300 font-mono">{fifties}</td>
                                                                    <td className="px-3 py-2 text-right text-slate-400 font-mono">{fours}</td>
                                                                    <td className="px-3 py-2 text-right text-slate-400 font-mono">{sixes}</td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}

                                    {/* Bowling Career Records Table */}
                                    {playerProfileData.bowling_career?.stats?.length > 0 && (
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <h4 className="text-xs font-black text-white uppercase tracking-wider flex items-center gap-1.5">
                                                    <Flame className="w-3.5 h-3.5 text-rose-400" /> Bowling Career Records
                                                </h4>
                                            </div>
                                            <div className="overflow-x-auto rounded-2xl border border-white/10 bg-slate-950/60">
                                                <table className="w-full text-left text-xs whitespace-nowrap">
                                                    <thead>
                                                        <tr className="border-b border-white/10 bg-white/5 text-slate-400 font-black text-[10px] uppercase">
                                                            <th className="px-3 py-2.5">Format</th>
                                                            <th className="px-3 py-2.5 text-right">Mat</th>
                                                            <th className="px-3 py-2.5 text-right">Balls</th>
                                                            <th className="px-3 py-2.5 text-right">Runs</th>
                                                            <th className="px-3 py-2.5 text-right font-black text-rose-400">Wkts</th>
                                                            <th className="px-3 py-2.5 text-right text-amber-400">BBI</th>
                                                            <th className="px-3 py-2.5 text-right text-emerald-400">Econ</th>
                                                            <th className="px-3 py-2.5 text-right text-sky-400">Avg</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-white/5 font-medium">
                                                        {playerProfileData.bowling_career.stats.map((row, idx) => {
                                                            const fmt = row.format || row.label || `Format ${idx + 1}`;
                                                            const mat = row.matches ?? (row.values ? row.values[0] : '-');
                                                            const balls = row.balls ?? (row.values ? row.values[1] : '-');
                                                            const runs = row.runs ?? (row.values ? row.values[2] : '-');
                                                            const wkts = row.wickets ?? (row.values ? row.values[3] : 0);
                                                            const bbi = row.best_bowling ?? (row.values ? row.values[4] : '-');
                                                            const econ = row.economy ?? (row.values ? row.values[5] : '-');
                                                            const avg = row.average ?? (row.values ? row.values[6] : '-');
                                                            return (
                                                                <tr key={idx} className="hover:bg-white/5 transition">
                                                                    <td className="px-3 py-2 font-black text-white">{fmt}</td>
                                                                    <td className="px-3 py-2 text-right text-slate-300 font-mono">{mat}</td>
                                                                    <td className="px-3 py-2 text-right text-slate-400 font-mono">{balls}</td>
                                                                    <td className="px-3 py-2 text-right text-slate-400 font-mono">{runs}</td>
                                                                    <td className="px-3 py-2 text-right font-black text-rose-400 font-mono">{wkts}</td>
                                                                    <td className="px-3 py-2 text-right font-bold text-amber-400 font-mono">{bbi}</td>
                                                                    <td className="px-3 py-2 text-right font-bold text-emerald-400 font-mono">{econ}</td>
                                                                    <td className="px-3 py-2 text-right font-bold text-sky-400 font-mono">{avg}</td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="py-12 text-center text-xs text-slate-400">
                                    Player records could not be loaded. Please try again.
                                </div>
                            )}
                        </div>

                        {/* Modal Footer */}
                        <div className="p-4 bg-slate-950/80 border-t border-white/10 flex items-center justify-between">
                            <span className="text-[11px] text-slate-400 font-medium">
                                Source: Verified International & Domestic Records
                            </span>
                            <button
                                onClick={() => {
                                    setSelectedPlayer(null);
                                    setPlayerProfileData(null);
                                }}
                                className="px-5 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition"
                            >
                                Close
                            </button>
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
function MatchCard({ match, type, onSelectSeries, onQuickScorecard, highlightedPlayer = null }) {
    const stLower = (match.status_text || '').toLowerCase();
    const isInningsBreak = ['innings break', 'inn break', 'break', 'lunch', 'tea', 'dinner', 'strategic timeout', 'drinks'].some(w => stLower.includes(w));
    const hasLiveScore = Array.isArray(match.score) && match.score.length > 0;
    const isLive = type === 'live' || (type === 'upcoming' && hasLiveScore) || isInningsBreak;
    const isUpcoming = type === 'upcoming' && !hasLiveScore && !isInningsBreak;
    const isRecent = type === 'recent' && !isInningsBreak;
    const isArchives = type === 'archives' && !isInningsBreak;
    const isDismissed = Boolean(match.is_dismissed || stLower === 'match dismissed');

    // Format teams & scores cleanly
    const scores = match.score || [];
    const seriesTitle = match.series || match.series_name;

    return (
        <div className={`rounded-3xl border transition-all duration-300 flex flex-col justify-between overflow-hidden group relative bg-[#131b2e] ${
            isLive
                ? (isInningsBreak
                    ? 'border-amber-500/30 hover:border-amber-500/60 shadow-xl hover:shadow-2xl hover:shadow-amber-500/10'
                    : 'border-rose-500/30 hover:border-rose-500/60 shadow-xl hover:shadow-2xl hover:shadow-rose-500/10')
                : isDismissed
                    ? 'border-rose-500/20 hover:border-rose-500/40 shadow-lg'
                    : 'border-white/10 hover:border-indigo-500/50 shadow-xl hover:shadow-2xl hover:shadow-indigo-500/10'
        }`}>
            {/* Top Indicator Strip for Live Matches */}
            {isLive && (
                <div className={`h-1 w-full ${isInningsBreak ? 'bg-gradient-to-r from-amber-500 via-orange-500 to-amber-400' : 'bg-gradient-to-r from-rose-500 via-red-500 to-amber-500'}`}></div>
            )}

            <div className="p-5 space-y-3.5">
                {/* Header Tag Row */}
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-1.5 flex-wrap">
                        {highlightedPlayer && (
                            (match.name || '').toLowerCase().includes((highlightedPlayer.country || '').toLowerCase()) ||
                            (Array.isArray(match.teams) && match.teams.some(t => (t || '').toLowerCase().includes((highlightedPlayer.country || '').toLowerCase())))
                        ) && (
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[10px] font-black bg-indigo-500/25 text-indigo-300 border border-indigo-500/40 shadow-xs">
                                🏏 {highlightedPlayer.name}'s Team
                            </span>
                        )}
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
                        {isInningsBreak && (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                <span className="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                INNINGS BREAK
                            </span>
                        )}
                        {!isInningsBreak && isLive && (
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
                        {isArchives && (
                            <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-black border ${
                                isDismissed
                                    ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                                    : match.status === 'abandoned'
                                        ? 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                                        : 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40'
                            }`}>
                                {isDismissed ? 'DISMISSED' : match.status === 'abandoned' ? 'ABANDONED' : 'ARCHIVE'}
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
                            const isMatchFinished = isRecent || isArchives || (match.matchEnded ?? false) || ['won by', ' won', 'beat', 'tied', 'drawn', 'complete', 'result'].some(w => (match.status_text || '').toLowerCase().includes(w));
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
                ) : (match.status_text && (match.status === 'abandoned' || ['abandon', 'cancel', 'no result'].some(w => match.status_text.toLowerCase().includes(w)))) ? (
                    <div className="p-3 bg-amber-500/10 rounded-2xl border border-amber-500/30 flex items-center gap-2.5 text-xs text-amber-300 font-bold">
                        <AlertCircle className="w-4 h-4 text-amber-400 shrink-0" />
                        <div className="min-w-0">
                            <span className="text-[10px] uppercase font-black tracking-wider text-amber-400 block">Match Status</span>
                            <span className="truncate block text-white font-black">{match.status_text}</span>
                        </div>
                    </div>
                ) : (match.status_text && !isRecent && !isArchives && (['opt to', 'elected to', 'chose to'].some(w => match.status_text.toLowerCase().includes(w)) || (match.status_text.toLowerCase().includes('toss') && !match.status_text.toLowerCase().includes('abandon')))) ? (
                    <div className="p-3 bg-amber-500/15 rounded-2xl border border-amber-500/30 flex items-center gap-2.5 text-xs text-amber-300 font-bold">
                        <Flame className="w-4 h-4 text-amber-400 shrink-0" />
                        <div className="min-w-0">
                            <span className="text-[10px] uppercase font-black tracking-wider text-amber-400 block">Toss Decision</span>
                            <span className="truncate block text-white font-black">{match.status_text}</span>
                        </div>
                    </div>
                ) : isUpcoming && match.dateTimeGMT ? (
                    <div className="p-3 bg-indigo-500/10 rounded-2xl border border-indigo-500/20 flex items-center gap-2 text-xs text-indigo-300 font-semibold">
                        <Clock className="w-4 h-4 text-indigo-400 shrink-0" />
                        <span>Match Starts: {match.dateTimeGMT} GMT</span>
                    </div>
                ) : (
                    /* Default Teams View */
                    <div className="p-3 bg-slate-950/60 rounded-2xl border border-white/5 flex items-center justify-between text-xs text-slate-300 font-bold">
                        <span>{match.teams && match.teams.length >= 2 ? `${match.teams[0]} vs ${match.teams[1]}` : 'Match Scheduled'}</span>
                        <span className="text-[10px] text-slate-500 font-normal">Starts soon</span>
                    </div>
                )}

                {/* Status Text Banner */}
                {match.status_text && (
                    <div className={`p-2.5 rounded-xl text-xs font-bold flex items-center justify-between gap-2 ${
                        isDismissed
                            ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30'
                            : isLive
                                ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30'
                                : isRecent
                                    ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30'
                                    : 'bg-slate-950/80 text-slate-300 border border-white/10'
                    }`}>
                        <div className="flex items-center gap-2 min-w-0">
                            <span className="shrink-0">
                                {isDismissed ? (
                                    <AlertCircle className="w-3.5 h-3.5 text-rose-400" />
                                ) : isLive ? (
                                    <Flame className="w-3.5 h-3.5 text-amber-400" />
                                ) : (
                                    <Shield className="w-3.5 h-3.5 text-emerald-400" />
                                )}
                            </span>
                            <span className="truncate">{match.status_text}</span>
                        </div>
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
