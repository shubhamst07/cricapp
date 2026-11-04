import React, { useState, useEffect, useRef } from 'react';
import {
    ArrowLeft,
    RefreshCw,
    MapPin,
    Trophy,
    Shield,
    Calendar,
    Activity,
    FileText,
    Info,
    CheckCircle2,
    X,
    User,
    Loader2,
    MessageSquare,
    Flame,
    Heart,
    Share2,
    Send,
    Image as ImageIcon,
    Sparkles,
    ExternalLink,
    Play,
    Volume2
} from 'lucide-react';

export default function MatchDetailApp({ initialMatch, initialScorecard, matchId }) {
    const [match, setMatch] = useState(initialMatch || {});
    const [scorecard, setScorecard] = useState(initialScorecard || {});
    const [activeTab, setActiveTab] = useState('scorecard'); // Default or switchable: 'scorecard', 'live', 'buzz', 'info'
    const [selectedInningIdx, setSelectedInningIdx] = useState(0);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [lastRefreshMsg, setLastRefreshMsg] = useState(null);

    // Fan Buzz & Social Posts State
    const [posts, setPosts] = useState([]);
    const [postsLoading, setPostsLoading] = useState(false);
    const [newPostContent, setNewPostContent] = useState('');
    const [newPostName, setNewPostName] = useState(() => localStorage.getItem('cricket_fan_name') || '');
    const [newPostInsta, setNewPostInsta] = useState('');
    const [newPostMedia, setNewPostMedia] = useState('');
    const [selectedImageFile, setSelectedImageFile] = useState(null);
    const [imagePreviewUrl, setImagePreviewUrl] = useState(null);
    const fileInputRef = useRef(null);
    const [newPostTeam, setNewPostTeam] = useState('');
    const [isPosting, setIsPosting] = useState(false);
    const [postSuccessMsg, setPostSuccessMsg] = useState(null);
    const [feedFilter, setFeedFilter] = useState('all'); // 'all', 'instagram', 'meme'
    const [likedPostIds, setLikedPostIds] = useState(() => {
        try {
            return JSON.parse(localStorage.getItem('cricket_liked_posts') || '[]');
        } catch {
            return [];
        }
    });

    const handleImageSelect = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            alert('Please select a valid image file (PNG, JPG, JPEG, WEBP, GIF).');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            alert('Image size exceeds 10MB limit.');
            return;
        }

        setSelectedImageFile(file);
        const preview = URL.createObjectURL(file);
        setImagePreviewUrl(preview);
        setNewPostMedia('');
    };

    const handleRemoveSelectedImage = () => {
        if (imagePreviewUrl) {
            URL.revokeObjectURL(imagePreviewUrl);
        }
        setSelectedImageFile(null);
        setImagePreviewUrl(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    useEffect(() => {
        fetchPosts();
    }, [matchId]);

    const fetchPosts = async () => {
        try {
            setPostsLoading(true);
            const res = await fetch(`/api/matches/${matchId}/posts`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.status === 'success') {
                    setPosts(json.data || []);
                }
            }
        } catch (e) {
            console.error('Failed to load posts', e);
        } finally {
            setPostsLoading(false);
        }
    };

    const handleCreatePost = async (e) => {
        e.preventDefault();
        if (!newPostContent.trim()) return;

        setIsPosting(true);
        setPostSuccessMsg(null);
        try {
            const author = newPostName.trim() || 'Cricket Fan';
            localStorage.setItem('cricket_fan_name', author);

            const formData = new FormData();
            formData.append('content', newPostContent);
            formData.append('user_name', author);
            if (newPostInsta.trim()) formData.append('insta_url', newPostInsta.trim());
            if (newPostMedia.trim()) formData.append('media_url', newPostMedia.trim());
            if (newPostTeam.trim()) formData.append('team_tag', newPostTeam.trim());
            if (selectedImageFile) {
                formData.append('image', selectedImageFile);
                formData.append('post_type', 'meme');
            }

            const res = await fetch(`/api/matches/${matchId}/posts`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                body: formData
            });

            if (res.ok) {
                const json = await res.json();
                if (json.status === 'success' && json.data) {
                    setPosts(prev => [json.data, ...prev]);
                    setNewPostContent('');
                    setNewPostInsta('');
                    setNewPostMedia('');
                    handleRemoveSelectedImage();
                    setPostSuccessMsg('Your post & photo/meme are live in Fan Buzz! 🔥');
                    setTimeout(() => setPostSuccessMsg(null), 3500);
                }
            }
        } catch (err) {
            console.error('Post error', err);
        } finally {
            setIsPosting(false);
        }
    };

    const handleLikePost = async (postId) => {
        if (likedPostIds.includes(postId)) return;

        setPosts(prev => prev.map(p => p.id === postId ? { ...p, likes_count: (p.likes_count || 0) + 1 } : p));
        const updated = [...likedPostIds, postId];
        setLikedPostIds(updated);
        localStorage.setItem('cricket_liked_posts', JSON.stringify(updated));

        try {
            await fetch(`/api/posts/${postId}/like`, {
                method: 'POST',
                headers: { 'Accept': 'application/json' }
            });
        } catch (e) {
            // ignore
        }
    };

    const getInstaEmbedUrl = (url) => {
        if (!url) return null;
        try {
            const match = url.match(/instagram\.com\/(p|reel|tv)\/([a-zA-Z0-9_\-]+)/);
            if (match && match[2]) {
                return `https://www.instagram.com/${match[1]}/${match[2]}/embed`;
            }
        } catch {
            return null;
        }
        return null;
    };

    // Player Career Profile Modal states
    const [selectedPlayer, setSelectedPlayer] = useState(null);
    const [playerProfile, setPlayerProfile] = useState(null);
    const [playerLoading, setPlayerLoading] = useState(false);
    const [playerError, setPlayerError] = useState(null);

    const handlePlayerClick = async (playerId, name) => {
        if (!name && !playerId) return;
        const cleanName = name || 'Player';
        setSelectedPlayer({ id: playerId, name: cleanName });
        setPlayerLoading(true);
        setPlayerError(null);
        setPlayerProfile(null);

        try {
            const queryId = playerId || '0';
            const res = await fetch(`/api/player/${queryId}?name=${encodeURIComponent(cleanName)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) {
                throw new Error(`HTTP error ${res.status}`);
            }
            const data = await res.json();
            if (data.status === 'success' && data.data) {
                setPlayerProfile(data.data);
            } else {
                setPlayerError(data.message || 'Player record could not be loaded.');
            }
        } catch (err) {
            setPlayerError('Unable to fetch player profile at this moment.');
        } finally {
            setPlayerLoading(false);
        }
    };

    const closePlayerModal = () => {
        setSelectedPlayer(null);
        setPlayerProfile(null);
        setPlayerError(null);
    };

    const handleRefresh = async () => {
        setIsRefreshing(true);
        setLastRefreshMsg(null);
        try {
            const res = await fetch(`/api/matches/${matchId}`, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const updatedMatch = await res.json();
                setMatch(updatedMatch);
            }
            const scRes = await fetch(`/api/matches/${matchId}/scorecard`, { headers: { 'Accept': 'application/json' } });
            if (scRes.ok) {
                const updatedSc = await scRes.json();
                setScorecard(updatedSc);
            }
            setLastRefreshMsg('Refreshed just now');
        } catch (err) {
            console.error('Refresh error:', err);
        } finally {
            setIsRefreshing(false);
        }
    };

    const scData = scorecard?.data || {};
    const innings = scData.innings || [];
    const currentBatting = scData.batting || [];
    const currentBowling = scData.bowling || [];
    const pom = match.player_of_the_match || scData.player_of_the_match;

    // Current selected inning data (for Scorecard tab)
    const activeInning = innings[selectedInningIdx] || (innings.length > 0 ? innings[0] : null);

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
            {/* Top Navigation & Actions */}
            <div className="flex items-center justify-between">
                <a
                    href="/live-scores"
                    className="inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition"
                >
                    <ArrowLeft className="w-4 h-4 mr-1.5" /> Back to Live Scores
                </a>
                <div className="flex items-center gap-3">
                    {lastRefreshMsg && (
                        <span className="text-xs text-emerald-600 font-medium">{lastRefreshMsg}</span>
                    )}
                    <button
                        type="button"
                        onClick={handleRefresh}
                        disabled={isRefreshing}
                        className="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition disabled:opacity-50"
                    >
                        <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
                        {isRefreshing ? 'Refreshing...' : 'Refresh Score'}
                    </button>
                </div>
            </div>

            {/* Match Header Hero Card */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                <div className="flex flex-wrap items-center gap-2 mb-3">
                    <span className="px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-indigo-100 text-indigo-800">
                        {match.matchType || 'Match'}
                    </span>
                    {match.season && (
                        <span className="px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                            Season {match.season}
                        </span>
                    )}
                    {match.status && (
                        <span className={`px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider ${
                            match.status === 'live'
                                ? 'bg-rose-100 text-rose-800 animate-pulse'
                                : (match.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800')
                        }`}>
                            {match.status}
                        </span>
                    )}
                </div>

                <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900 leading-tight">
                    {match.name || match.title || 'Match Details'}
                </h1>

                <div className="flex flex-wrap items-center gap-4 mt-3 text-xs text-gray-500">
                    <span className="flex items-center">
                        <MapPin className="w-4 h-4 mr-1 text-gray-400" />
                        {match.venue || 'Venue TBA'}
                    </span>
                    {match.dateTimeGMT && (
                        <span className="flex items-center">
                            <Calendar className="w-4 h-4 mr-1 text-gray-400" />
                            {match.dateTimeGMT} GMT
                        </span>
                    )}
                </div>

                {/* Status / Outcome Banner */}
                {match.status_text && (
                    <div className="mt-5 p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-sm font-semibold text-amber-900 flex items-center gap-2">
                        <Shield className="w-4 h-4 text-amber-600 shrink-0" />
                        <span>{match.status_text}</span>
                    </div>
                )}

                {/* Player of the Match / Man of the Match Hero Banner */}
                {pom && pom.name && (
                    <div className="mt-4 p-4 bg-gradient-to-r from-amber-500/15 via-amber-400/10 to-yellow-500/5 border border-amber-300 rounded-2xl flex items-center justify-between gap-4 shadow-xs">
                        <div className="flex items-center gap-3.5 min-w-0">
                            <div className="relative shrink-0">
                                {pom.image ? (
                                    <img
                                        src={pom.image}
                                        alt={pom.name}
                                        className="w-13 h-13 rounded-full object-cover border-2 border-amber-400 shadow-sm"
                                        onError={(e) => { e.target.style.display = 'none'; }}
                                    />
                                ) : (
                                    <div className="w-13 h-13 rounded-full bg-amber-100 border-2 border-amber-400 flex items-center justify-center text-amber-700 font-bold">
                                        <User className="w-6 h-6" />
                                    </div>
                                )}
                                <span className="absolute -bottom-1 -right-1 p-1 bg-amber-500 text-white rounded-full shadow-xs">
                                    <Trophy className="w-3 h-3" />
                                </span>
                            </div>
                            <div className="min-w-0">
                                <div className="flex items-center gap-1.5">
                                    <span className="text-[11px] font-black uppercase tracking-wider text-amber-800 bg-amber-200/80 px-2.5 py-0.5 rounded-full inline-flex items-center gap-1">
                                        <Trophy className="w-3 h-3" />
                                        {pom.award || 'Player of the Match'}
                                    </span>
                                </div>
                                <h3 className="text-base sm:text-lg font-black text-gray-900 truncate mt-1">
                                    {pom.name}
                                </h3>
                                {pom.stats && (
                                    <p className="text-xs font-semibold text-gray-700 mt-0.5">
                                        Performance: <span className="text-amber-900 font-black">{pom.stats}</span>
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="hidden sm:flex items-center gap-2 text-amber-900 font-extrabold text-xs bg-amber-200/70 px-3.5 py-2 rounded-xl border border-amber-300 shrink-0">
                            <Trophy className="w-4 h-4 text-amber-700" />
                            <span>Man of the Match</span>
                        </div>
                    </div>
                )}
            </div>

            {/* Cricbuzz Style Navigation Tabs */}
            <div className="border-b border-gray-200 bg-white rounded-t-2xl px-6 pt-3 shadow-sm">
                <nav className="flex space-x-8" aria-label="Tabs">
                    <button
                        type="button"
                        onClick={() => setActiveTab('scorecard')}
                        className={`inline-flex items-center py-4 px-1 border-b-2 font-bold text-sm transition ${
                            activeTab === 'scorecard'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        }`}
                    >
                        <FileText className="w-4 h-4 mr-2" />
                        Full Scorecard
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('live')}
                        className={`inline-flex items-center py-4 px-1 border-b-2 font-bold text-sm transition ${
                            activeTab === 'live'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        }`}
                    >
                        <Activity className="w-4 h-4 mr-2" />
                        Live Summary
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('buzz')}
                        className={`inline-flex items-center py-4 px-1 border-b-2 font-bold text-sm transition relative ${
                            activeTab === 'buzz'
                                ? 'border-rose-600 text-rose-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        }`}
                    >
                        <Flame className={`w-4 h-4 mr-1.5 ${activeTab === 'buzz' ? 'text-rose-600 fill-rose-600' : 'text-rose-500'}`} />
                        Fan Buzz & Reels
                        {posts.length > 0 && (
                            <span className="ml-1.5 px-2 py-0.5 rounded-full text-[11px] font-black bg-rose-100 text-rose-700">
                                {posts.length}
                            </span>
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('info')}
                        className={`inline-flex items-center py-4 px-1 border-b-2 font-bold text-sm transition ${
                            activeTab === 'info'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        }`}
                    >
                        <Info className="w-4 h-4 mr-2" />
                        Match Info
                    </button>
                </nav>
            </div>

            {/* ==================== TAB 1: FULL SCORECARD (Cricbuzz Style) ==================== */}
            {activeTab === 'scorecard' && (
                <div className="space-y-6">
                    {/* Innings Selector Pills */}
                    {innings.length > 0 ? (
                        <div>
                            <div className="flex flex-wrap gap-2">
                                {innings.map((inn, idx) => (
                                    <button
                                        key={idx}
                                        type="button"
                                        onClick={() => setSelectedInningIdx(idx)}
                                        className={`px-4 py-2.5 rounded-xl font-bold text-xs transition flex items-center gap-2 shadow-sm ${
                                            selectedInningIdx === idx
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'
                                        }`}
                                    >
                                        <span>{inn.team || `Innings ${idx + 1}`}</span>
                                        <span className={`px-2 py-0.5 rounded-md text-[11px] font-black ${
                                            selectedInningIdx === idx ? 'bg-indigo-800/60 text-white' : 'bg-gray-100 text-gray-800'
                                        }`}>
                                            {inn.score} ({inn.overs} ov)
                                        </span>
                                    </button>
                                ))}
                            </div>

                            {/* Active Inning Detailed Tables */}
                            {activeInning && (
                                <div className="space-y-6 mt-4">
                                    {/* Batting Table */}
                                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                                        <div className="px-6 py-4 border-b border-gray-100 bg-gray-50/70 flex justify-between items-center">
                                            <h2 className="text-base font-bold text-gray-900">
                                                {activeInning.team} Batting
                                            </h2>
                                            <span className="text-sm font-black text-gray-900 bg-indigo-50 text-indigo-900 px-3 py-1 rounded-lg">
                                                {activeInning.score} ({activeInning.overs} Ov, RR: {activeInning.run_rate || 'N/A'})
                                            </span>
                                        </div>
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                                <thead className="bg-gray-50 font-semibold text-gray-600 text-xs uppercase tracking-wider">
                                                    <tr>
                                                        <th className="px-6 py-3.5 text-left">Batter</th>
                                                        <th className="px-4 py-3.5 text-right">Runs</th>
                                                        <th className="px-4 py-3.5 text-right">Balls</th>
                                                        <th className="px-4 py-3.5 text-right">4s</th>
                                                        <th className="px-4 py-3.5 text-right">6s</th>
                                                        <th className="px-4 py-3.5 text-right">SR</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-100 bg-white">
                                                    {activeInning.batting?.map((player, idx) => (
                                                        <tr key={idx} className="hover:bg-gray-50/80 transition">
                                                            <td className="px-6 py-3.5">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handlePlayerClick(player.id, player.name)}
                                                                    className="font-bold text-gray-900 hover:text-indigo-600 transition text-left flex items-center gap-1.5 group cursor-pointer"
                                                                    title="Click to view career record"
                                                                >
                                                                    <span className="group-hover:underline underline-offset-2">{player.name}</span>
                                                                    {player.out === 'batting' && (
                                                                        <span className="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse" title="On Crease"></span>
                                                                    )}
                                                                </button>
                                                                <div className={`text-xs mt-0.5 ${
                                                                    player.out === 'batting' || player.out === 'not out'
                                                                        ? 'text-emerald-600 font-semibold'
                                                                        : 'text-gray-400'
                                                                }`}>
                                                                    {player.out}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3.5 text-right font-black text-gray-900 text-base">{player.runs ?? 0}</td>
                                                            <td className="px-4 py-3.5 text-right text-gray-500 font-medium">{player.balls ?? 0}</td>
                                                            <td className="px-4 py-3.5 text-right text-gray-500">{player.fours ?? 0}</td>
                                                            <td className="px-4 py-3.5 text-right text-gray-500">{player.sixes ?? 0}</td>
                                                            <td className="px-4 py-3.5 text-right text-gray-700 font-semibold">{player.strikeRate ?? 0}</td>
                                                        </tr>
                                                    ))}

                                                    {/* Extras Row */}
                                                    {activeInning.extras && (
                                                        <tr className="bg-gray-50/50 text-xs text-gray-600 font-medium">
                                                            <td className="px-6 py-3 font-semibold text-gray-700">Extras</td>
                                                            <td colSpan={5} className="px-4 py-3 text-right">
                                                                <span className="font-bold text-gray-900 mr-2">{activeInning.extras.total ?? 0}</span>
                                                                <span className="text-gray-500">
                                                                    (b {activeInning.extras.byes ?? 0}, lb {activeInning.extras.legByes ?? 0}, w {activeInning.extras.wides ?? 0}, nb {activeInning.extras.noBalls ?? 0}, p {activeInning.extras.penalty ?? 0})
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    )}

                                                    {/* Total Row */}
                                                    <tr className="bg-indigo-50/40 text-sm font-bold text-gray-900 border-t border-gray-200">
                                                        <td className="px-6 py-3.5">Total</td>
                                                        <td colSpan={5} className="px-4 py-3.5 text-right">
                                                            <span className="text-base font-black text-indigo-900">{activeInning.score}</span>
                                                            <span className="text-xs text-gray-500 ml-2 font-normal">({activeInning.overs} Overs, RR: {activeInning.run_rate || 'N/A'})</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        {/* Did Not Bat */}
                                        {activeInning.did_not_bat && activeInning.did_not_bat.length > 0 && (
                                            <div className="px-6 py-3.5 border-t border-gray-100 bg-gray-50/30 flex flex-wrap items-center gap-2 text-xs">
                                                <span className="font-bold text-gray-700">Yet to Bat:</span>
                                                {activeInning.did_not_bat.map((p, i) => {
                                                    const pName = typeof p === 'string' ? p : (p.name || 'Player');
                                                    const pId = typeof p === 'string' ? null : p.id;
                                                    return (
                                                        <button
                                                            key={i}
                                                            type="button"
                                                            onClick={() => handlePlayerClick(pId, pName)}
                                                            className="px-2.5 py-1 bg-white border border-gray-200 hover:border-indigo-400 hover:text-indigo-600 rounded-lg text-gray-700 font-medium shadow-xs transition cursor-pointer"
                                                            title="Click to view career record"
                                                        >
                                                            {pName}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>

                                    {/* Fall of Wickets */}
                                    {activeInning.fall_of_wickets && activeInning.fall_of_wickets.length > 0 && (
                                        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                                            <h3 className="text-xs font-bold text-gray-600 uppercase tracking-wider mb-3">Fall of Wickets</h3>
                                            <div className="flex flex-wrap gap-2 text-xs">
                                                {activeInning.fall_of_wickets.map((f, idx) => (
                                                    <div key={idx} className="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-xl flex items-center gap-1.5">
                                                        <span className="font-black text-rose-600">{f.score}-{f.wkt_nbr}</span>
                                                        <span className="text-gray-700 font-semibold">({f.batsman}, {f.over} ov)</span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Bowling Table */}
                                    {activeInning.bowling && activeInning.bowling.length > 0 && (
                                        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                                            <div className="px-6 py-4 border-b border-gray-100 bg-gray-50/70">
                                                <h2 className="text-base font-bold text-gray-900">Bowling</h2>
                                            </div>
                                            <div className="overflow-x-auto">
                                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                                    <thead className="bg-gray-50 font-semibold text-gray-600 text-xs uppercase tracking-wider">
                                                        <tr>
                                                            <th className="px-6 py-3.5 text-left">Bowler</th>
                                                            <th className="px-4 py-3.5 text-right">O</th>
                                                            <th className="px-4 py-3.5 text-right">M</th>
                                                            <th className="px-4 py-3.5 text-right">R</th>
                                                            <th className="px-4 py-3.5 text-right">W</th>
                                                            <th className="px-4 py-3.5 text-right">Econ</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-gray-100 bg-white">
                                                        {activeInning.bowling.map((bowler, idx) => (
                                                            <tr key={idx} className="hover:bg-gray-50/80 transition">
                                                                <td className="px-6 py-3.5">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => handlePlayerClick(bowler.id, bowler.name || bowler.bowler)}
                                                                        className="font-bold text-gray-900 hover:text-indigo-600 hover:underline underline-offset-2 transition text-left cursor-pointer"
                                                                        title="Click to view career record"
                                                                    >
                                                                        {bowler.name || bowler.bowler}
                                                                    </button>
                                                                </td>
                                                                <td className="px-4 py-3.5 text-right text-gray-700 font-medium">{bowler.overs ?? 0}</td>
                                                                <td className="px-4 py-3.5 text-right text-gray-500">{bowler.maidens ?? 0}</td>
                                                                <td className="px-4 py-3.5 text-right text-gray-700 font-medium">{bowler.runs ?? 0}</td>
                                                                <td className="px-4 py-3.5 text-right font-black text-indigo-700 text-base">{bowler.wickets ?? 0}</td>
                                                                <td className="px-4 py-3.5 text-right text-gray-600 font-semibold">{bowler.economy ?? 0}</td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    ) : (
                        /* Fallback if full innings array is not available yet (e.g. beginning of match) */
                        <div className="space-y-6">
                            {currentBatting.length > 0 && (
                                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                                    <div className="px-6 py-4 border-b border-gray-100 bg-gray-50/70">
                                        <h2 className="text-base font-bold text-gray-900">Batting Scorecard</h2>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                                            <thead className="bg-gray-50 font-semibold text-gray-600 text-xs uppercase">
                                                <tr>
                                                    <th className="px-6 py-3 text-left">Batter</th>
                                                    <th className="px-4 py-3 text-right">Runs</th>
                                                    <th className="px-4 py-3 text-right">Balls</th>
                                                    <th className="px-4 py-3 text-right">4s</th>
                                                    <th className="px-4 py-3 text-right">6s</th>
                                                    <th className="px-4 py-3 text-right">SR</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100 bg-white">
                                                {currentBatting.map((player, idx) => (
                                                    <tr key={idx} className="hover:bg-gray-50">
                                                        <td className="px-6 py-3 font-semibold text-gray-900">
                                                            <button
                                                                type="button"
                                                                onClick={() => handlePlayerClick(player.id, player.name)}
                                                                className="hover:text-indigo-600 hover:underline transition text-left cursor-pointer font-bold"
                                                                title="Click to view career record"
                                                            >
                                                                {player.name}
                                                            </button>
                                                            {player.out && <span className="block text-xs font-normal text-gray-400">{player.out}</span>}
                                                        </td>
                                                        <td className="px-4 py-3 text-right font-black text-gray-900">{player.runs ?? 0}</td>
                                                        <td className="px-4 py-3 text-right text-gray-500">{player.balls ?? 0}</td>
                                                        <td className="px-4 py-3 text-right text-gray-500">{player.fours ?? 0}</td>
                                                        <td className="px-4 py-3 text-right text-gray-500">{player.sixes ?? 0}</td>
                                                        <td className="px-4 py-3 text-right text-gray-500">{player.strikeRate ?? 0}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            {currentBowling.length > 0 && (
                                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                                    <div className="px-6 py-4 border-b border-gray-100 bg-gray-50/70">
                                        <h2 className="text-base font-bold text-gray-900">Bowling Scorecard</h2>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                                            <thead className="bg-gray-50 font-semibold text-gray-600 text-xs uppercase">
                                                <tr>
                                                    <th className="px-6 py-3 text-left">Bowler</th>
                                                    <th className="px-4 py-3 text-right">O</th>
                                                    <th className="px-4 py-3 text-right">M</th>
                                                    <th className="px-4 py-3 text-right">R</th>
                                                    <th className="px-4 py-3 text-right">W</th>
                                                    <th className="px-4 py-3 text-right">Econ</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100 bg-white">
                                                {currentBowling.map((bowler, idx) => (
                                                    <tr key={idx} className="hover:bg-gray-50">
                                                        <td className="px-6 py-3 font-semibold text-gray-900">
                                                            <button
                                                                type="button"
                                                                onClick={() => handlePlayerClick(bowler.id, bowler.name || bowler.bowler)}
                                                                className="hover:text-indigo-600 hover:underline transition text-left cursor-pointer font-bold"
                                                                title="Click to view career record"
                                                            >
                                                                {bowler.name || bowler.bowler}
                                                            </button>
                                                        </td>
                                                        <td className="px-4 py-3 text-right text-gray-700">{bowler.overs ?? 0}</td>
                                                        <td className="px-4 py-3 text-right text-gray-500">{bowler.maidens ?? 0}</td>
                                                        <td className="px-4 py-3 text-right text-gray-700">{bowler.runs ?? 0}</td>
                                                        <td className="px-4 py-3 text-right font-black text-gray-900">{bowler.wickets ?? 0}</td>
                                                        <td className="px-4 py-3 text-right text-gray-500">{bowler.economy ?? 0}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}

            {/* ==================== TAB 2: LIVE SUMMARY ==================== */}
            {activeTab === 'live' && (
                <div className="space-y-6">
                    {/* Innings Scores Summary Cards */}
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 className="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <Activity className="w-5 h-5 text-indigo-600" /> Current Innings Scores
                        </h2>

                        {match.score && match.score.length > 0 ? (
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {match.score.map((inn, idx) => {
                                    const innOvers = inn.overs ?? inn.o;
                                    const hasInnOvers = innOvers && innOvers !== '0' && innOvers !== 0 && innOvers !== '0.0';
                                    const displayScore = inn.display_score || `${inn.runs ?? inn.r ?? 0}/${inn.wickets ?? inn.w ?? 0}`;
                                    return (
                                        <div key={idx} className="bg-gray-50 border border-gray-100 rounded-xl p-4 flex justify-between items-center">
                                            <div>
                                                <h3 className="font-bold text-gray-800 text-sm">{inn.batting_team || inn.inning || `Innings ${idx + 1}`}</h3>
                                                {hasInnOvers && (
                                                    <p className="text-xs text-gray-500 mt-0.5">{innOvers} overs</p>
                                                )}
                                            </div>
                                            <div className="text-2xl font-black text-gray-900">
                                                {displayScore}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-400">Score updates will appear here once available.</p>
                        )}
                    </div>

                    {/* Active Batters on Crease */}
                    {currentBatting.length > 0 && (
                        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div className="px-6 py-4 border-b border-gray-100 bg-gray-50/70 flex items-center justify-between">
                                <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Batters at the Crease
                                </h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50 font-semibold text-gray-600 text-xs uppercase tracking-wider">
                                        <tr>
                                            <th className="px-6 py-3 text-left">Batter</th>
                                            <th className="px-4 py-3 text-right">Runs</th>
                                            <th className="px-4 py-3 text-right">Balls</th>
                                            <th className="px-4 py-3 text-right">4s</th>
                                            <th className="px-4 py-3 text-right">6s</th>
                                            <th className="px-4 py-3 text-right">SR</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 bg-white">
                                        {currentBatting.map((player, idx) => (
                                            <tr key={idx} className="hover:bg-gray-50">
                                                <td className="px-6 py-3.5 font-bold text-gray-900">
                                                    <button
                                                        type="button"
                                                        onClick={() => handlePlayerClick(player.id, player.name)}
                                                        className="hover:text-indigo-600 hover:underline transition text-left cursor-pointer inline-flex items-center gap-1.5"
                                                        title="Click to view career record"
                                                    >
                                                        <span>{player.name}</span>
                                                    </button>
                                                    <span className="ml-2 text-xs text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md">
                                                        {player.out || 'batting'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3.5 text-right font-black text-gray-900 text-base">{player.runs ?? 0}</td>
                                                <td className="px-4 py-3.5 text-right text-gray-500">{player.balls ?? 0}</td>
                                                <td className="px-4 py-3.5 text-right text-gray-500">{player.fours ?? 0}</td>
                                                <td className="px-4 py-3.5 text-right text-gray-500">{player.sixes ?? 0}</td>
                                                <td className="px-4 py-3.5 text-right text-gray-700 font-semibold">{player.strikeRate ?? 0}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Active Bowler in Action */}
                    {currentBowling.length > 0 && (
                        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div className="px-6 py-4 border-b border-gray-100 bg-gray-50/70">
                                <h2 className="text-base font-bold text-gray-900">Bowlers</h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50 font-semibold text-gray-600 text-xs uppercase tracking-wider">
                                        <tr>
                                            <th className="px-6 py-3 text-left">Bowler</th>
                                            <th className="px-4 py-3 text-right">O</th>
                                            <th className="px-4 py-3 text-right">M</th>
                                            <th className="px-4 py-3 text-right">R</th>
                                            <th className="px-4 py-3 text-right">W</th>
                                            <th className="px-4 py-3 text-right">Econ</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 bg-white">
                                        {currentBowling.map((bowler, idx) => (
                                            <tr key={idx} className="hover:bg-gray-50">
                                                <td className="px-6 py-3.5 font-bold text-gray-900">
                                                    <button
                                                        type="button"
                                                        onClick={() => handlePlayerClick(bowler.id, bowler.name || bowler.bowler)}
                                                        className="hover:text-indigo-600 hover:underline transition text-left cursor-pointer"
                                                        title="Click to view career record"
                                                    >
                                                        {bowler.name || bowler.bowler}
                                                    </button>
                                                </td>
                                                <td className="px-4 py-3.5 text-right text-gray-700">{bowler.overs ?? 0}</td>
                                                <td className="px-4 py-3.5 text-right text-gray-500">{bowler.maidens ?? 0}</td>
                                                <td className="px-4 py-3.5 text-right text-gray-700">{bowler.runs ?? 0}</td>
                                                <td className="px-4 py-3.5 text-right font-black text-indigo-700">{bowler.wickets ?? 0}</td>
                                                <td className="px-4 py-3.5 text-right text-gray-500 font-semibold">{bowler.economy ?? 0}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Live Summary Social Buzz Quick Peek */}
                    <div className="bg-gradient-to-br from-white to-rose-50/30 rounded-2xl shadow-sm border border-rose-100 p-6 space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <div className="w-8 h-8 rounded-xl bg-rose-500 text-white flex items-center justify-center shadow-xs">
                                    <Flame className="w-4 h-4 fill-white" />
                                </div>
                                <div>
                                    <h3 className="text-base font-bold text-gray-900">Live Match Fan Buzz & Memes</h3>
                                    <p className="text-xs text-gray-500">Trending fan takes, memes & reels for this match</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setActiveTab('buzz')}
                                className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer flex items-center gap-1.5"
                            >
                                <span>Join the Banter & Post</span>
                                <span>→</span>
                            </button>
                        </div>

                        {posts.length > 0 ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                {posts.slice(0, 2).map((p, idx) => (
                                    <div key={idx} className="bg-white border border-gray-100 rounded-xl p-4 shadow-xs flex flex-col justify-between space-y-3">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <div className="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs flex items-center justify-center">
                                                    {(p.user_name || 'Fan')[0]}
                                                </div>
                                                <span className="font-bold text-gray-900 text-xs">{p.user_name}</span>
                                            </div>
                                            {p.team_tag && (
                                                <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700">
                                                    {p.team_tag}
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-xs text-gray-700 line-clamp-3 leading-relaxed">{p.content}</p>
                                        <div className="flex items-center justify-between text-xs text-gray-400 pt-1 border-t border-gray-50">
                                            <span className="flex items-center gap-1 text-rose-600 font-semibold">
                                                <Heart className="w-3.5 h-3.5 fill-rose-600" /> {p.likes_count || 0}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setActiveTab('buzz')}
                                                className="text-indigo-600 hover:underline font-semibold"
                                            >
                                                View Discussion
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-xs text-gray-400 italic">No posts yet. Be the first to share your opinion!</p>
                        )}
                    </div>
                </div>
            )}

            {/* ==================== TAB 3: FAN BUZZ & SOCIAL MEDIA ==================== */}
            {activeTab === 'buzz' && (
                <div className="space-y-6">
                    {/* Fan Buzz Hero Header */}
                    <div className="bg-gradient-to-r from-rose-600 via-pink-600 to-indigo-700 rounded-3xl p-6 md:p-8 text-white shadow-md relative overflow-hidden">
                        <div className="relative z-10 space-y-2 max-w-2xl">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 backdrop-blur-xs text-xs font-bold text-white uppercase tracking-wider">
                                <Sparkles className="w-3.5 h-3.5" />
                                Cricket Social Hub
                            </div>
                            <h2 className="text-2xl md:text-3xl font-black tracking-tight">
                                Live Match Fan Buzz & Memes
                            </h2>
                            <p className="text-xs md:text-sm text-pink-100 leading-relaxed">
                                Share your thoughts, drop trending match memes, post Instagram reels, and connect with cricket fans around the world in real time!
                            </p>
                        </div>
                    </div>

                    {/* Post Creation Box */}
                    <div className="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 space-y-4">
                        <form onSubmit={handleCreatePost} className="space-y-4">
                            <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                                <input
                                    type="text"
                                    placeholder="Your Name (e.g. Virat Fan, Dhoni Army)"
                                    value={newPostName}
                                    onChange={(e) => setNewPostName(e.target.value)}
                                    className="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:bg-white transition flex-1"
                                />

                                {match.teams && match.teams.length > 0 && (
                                    <select
                                        value={newPostTeam}
                                        onChange={(e) => setNewPostTeam(e.target.value)}
                                        className="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:bg-white transition"
                                    >
                                        <option value="">Cheering For (Optional)</option>
                                        {match.teams.map((t, i) => (
                                            <option key={i} value={t}>{t}</option>
                                        ))}
                                    </select>
                                )}
                            </div>

                            <div>
                                <textarea
                                    rows={3}
                                    placeholder="What's happening in the match? Share a thought, paste an Instagram Reel link, or drop a meme..."
                                    value={newPostContent}
                                    onChange={(e) => setNewPostContent(e.target.value)}
                                    className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:bg-white transition resize-none"
                                    required
                                />
                            </div>

                            {/* Optional Media & Instagram URL Inputs */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div className="flex items-center gap-2 px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-600 focus-within:ring-2 focus-within:ring-pink-500 focus-within:bg-white transition">
                                    <span className="font-bold text-pink-600 shrink-0">Insta URL:</span>
                                    <input
                                        type="url"
                                        placeholder="https://instagram.com/reel/..."
                                        value={newPostInsta}
                                        onChange={(e) => setNewPostInsta(e.target.value)}
                                        className="w-full bg-transparent border-none text-xs text-gray-800 placeholder-gray-400 focus:outline-none"
                                    />
                                </div>

                                <div className="flex items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-600 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:bg-white transition">
                                    <input
                                        type="file"
                                        ref={fileInputRef}
                                        accept="image/*"
                                        onChange={handleImageSelect}
                                        className="hidden"
                                        id="meme-photo-input"
                                    />

                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-xs shadow-xs transition shrink-0 cursor-pointer"
                                    >
                                        <ImageIcon className="w-3.5 h-3.5" />
                                        <span>Select Photo</span>
                                    </button>

                                    <span className="text-gray-300">|</span>

                                    <input
                                        type="url"
                                        placeholder="or paste image URL..."
                                        value={newPostMedia}
                                        onChange={(e) => {
                                            setNewPostMedia(e.target.value);
                                            if (e.target.value && selectedImageFile) {
                                                handleRemoveSelectedImage();
                                            }
                                        }}
                                        disabled={!!selectedImageFile}
                                        className="w-full bg-transparent border-none text-xs text-gray-800 placeholder-gray-400 focus:outline-none disabled:opacity-40 disabled:cursor-not-allowed"
                                    />
                                </div>
                            </div>

                            {/* Image Preview Box when user selects a photo */}
                            {imagePreviewUrl && (
                                <div className="relative inline-flex items-center gap-3 p-2 bg-indigo-50/70 border border-indigo-200 rounded-xl animate-in fade-in zoom-in-95">
                                    <div className="w-14 h-14 rounded-lg overflow-hidden border border-indigo-300 bg-gray-900 shrink-0 shadow-xs">
                                        <img
                                            src={imagePreviewUrl}
                                            alt="Selected Meme Preview"
                                            className="w-full h-full object-cover"
                                        />
                                    </div>
                                    <div className="text-xs pr-6">
                                        <span className="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-600 text-white rounded text-[10px] font-bold mb-0.5">
                                            Photo Selected
                                        </span>
                                        <p className="font-bold text-gray-800 truncate max-w-[200px] sm:max-w-xs">
                                            {selectedImageFile?.name}
                                        </p>
                                        <p className="text-[10px] text-gray-500 font-medium">
                                            {selectedImageFile ? (selectedImageFile.size / 1024).toFixed(1) + ' KB' : ''} • Ready to post
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleRemoveSelectedImage}
                                        className="absolute top-2 right-2 p-1 bg-white hover:bg-rose-50 text-gray-400 hover:text-rose-600 border border-gray-200 rounded-lg shadow-xs transition cursor-pointer"
                                        title="Remove Photo"
                                    >
                                        <X className="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            )}

                            {/* Quick Match Tags */}
                            <div className="flex flex-wrap items-center gap-1.5 pt-1">
                                <span className="text-[11px] font-bold text-gray-400 mr-1">Quick Tags:</span>
                                {['#Sixer 🔥', '#WhatACatch 🦅', '#InForm 🏏', '#MatchTurningMoment ⚡', '#CheeringForWin 🏆'].map((tag, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        onClick={() => setNewPostContent(prev => prev ? `${prev} ${tag}` : tag)}
                                        className="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 rounded-lg text-[11px] font-semibold text-gray-700 transition cursor-pointer"
                                    >
                                        {tag}
                                    </button>
                                ))}
                            </div>

                            {postSuccessMsg && (
                                <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs font-bold text-emerald-800 flex items-center gap-2">
                                    <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                                    <span>{postSuccessMsg}</span>
                                </div>
                            )}

                            <div className="flex items-center justify-between pt-2 border-t border-gray-100">
                                <span className="text-xs text-gray-400">
                                    No login required — instant posting!
                                </span>
                                <button
                                    type="submit"
                                    disabled={isPosting || !newPostContent.trim()}
                                    className="px-5 py-2.5 bg-gradient-to-r from-rose-600 to-pink-600 hover:from-rose-700 hover:to-pink-700 text-white rounded-xl text-xs font-bold shadow-md transition disabled:opacity-50 cursor-pointer flex items-center gap-2"
                                >
                                    <Send className="w-3.5 h-3.5" />
                                    {isPosting ? 'Publishing...' : 'Post to Fan Buzz'}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Filter Pills */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            {[
                                { key: 'all', label: `All Buzz (${posts.length})` },
                                { key: 'instagram', label: 'Instagram Reels 🎬' },
                                { key: 'meme', label: 'Memes & Pics 😂' },
                            ].map((f) => (
                                <button
                                    key={f.key}
                                    type="button"
                                    onClick={() => setFeedFilter(f.key)}
                                    className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer ${
                                        feedFilter === f.key
                                            ? 'bg-rose-600 text-white shadow-xs'
                                            : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'
                                    }`}
                                >
                                    {f.label}
                                </button>
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={fetchPosts}
                            className="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 cursor-pointer"
                        >
                            <RefreshCw className="w-3.5 h-3.5" /> Refresh Feed
                        </button>
                    </div>

                    {/* Feed List */}
                    {postsLoading && (
                        <div className="py-16 text-center space-y-3">
                            <Loader2 className="w-8 h-8 text-rose-600 animate-spin mx-auto" />
                            <p className="text-xs font-bold text-gray-500">Loading live fan banter & reels...</p>
                        </div>
                    )}

                    {!postsLoading && (
                        <div className="space-y-4">
                            {posts
                                .filter(p => {
                                    if (feedFilter === 'instagram') return p.post_type === 'instagram' || p.insta_url;
                                    if (feedFilter === 'meme') return p.post_type === 'meme' || p.media_url;
                                    return true;
                                })
                                .map((post) => {
                                    const isLiked = likedPostIds.includes(post.id);
                                    const instaEmbedUrl = getInstaEmbedUrl(post.insta_url);

                                    return (
                                        <div
                                            key={post.id}
                                            className="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 sm:p-6 space-y-4 transition hover:border-gray-200"
                                        >
                                            {/* Post Author Bar */}
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-10 h-10 rounded-2xl bg-gradient-to-tr from-rose-500 to-indigo-600 text-white font-black text-sm flex items-center justify-center shadow-xs">
                                                        {(post.user_name || 'Fan')[0]?.toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <h4 className="font-extrabold text-sm text-gray-900 leading-tight">
                                                            {post.user_name}
                                                        </h4>
                                                        <p className="text-[11px] text-gray-400">
                                                            {new Date(post.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} • Cricket Fan
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="flex items-center gap-2">
                                                    {post.team_tag && (
                                                        <span className="px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700">
                                                            {post.team_tag}
                                                        </span>
                                                    )}
                                                    {(post.post_type === 'instagram' || post.insta_url) && (
                                                        <span className="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-gradient-to-r from-pink-500 to-rose-500 text-white shadow-2xs">
                                                            Instagram Reel 🎬
                                                        </span>
                                                    )}
                                                    {post.post_type === 'meme' && (
                                                        <span className="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-50 text-amber-700">
                                                            Meme 😂
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Content */}
                                            <p className="text-sm text-gray-800 leading-relaxed font-normal whitespace-pre-line">
                                                {post.content}
                                            </p>

                                            {/* Media / Meme Image */}
                                            {post.media_url && !post.insta_url && (
                                                <div className="rounded-2xl overflow-hidden border border-gray-100 bg-gray-900 max-h-96 relative group">
                                                    <img
                                                        src={post.media_url}
                                                        alt="Match Meme / Media"
                                                        className="w-full h-full object-cover"
                                                        onError={(e) => { e.currentTarget.style.display = 'none'; }}
                                                    />
                                                    {post.post_type === 'meme' && (
                                                        <div className="absolute top-3 left-3 px-2.5 py-1 bg-black/60 backdrop-blur-md rounded-lg text-[10px] font-bold text-amber-300 border border-amber-300/30">
                                                            😂 Match Meme
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                            {/* Instagram Reel Player Card */}
                                            {post.insta_url && (
                                                <div className="rounded-3xl border border-pink-200/80 bg-gradient-to-br from-pink-50/60 via-purple-50/20 to-rose-50/50 p-4 space-y-3 shadow-xs">
                                                    {/* Instagram Profile Header */}
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-2">
                                                            <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-amber-500 via-rose-500 to-purple-600 p-0.5 shadow-xs flex items-center justify-center">
                                                                <div className="w-full h-full bg-white rounded-full flex items-center justify-center text-rose-600 font-black text-[11px]">
                                                                    IG
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <div className="flex items-center gap-1">
                                                                    <span className="font-extrabold text-xs text-gray-900">
                                                                        {post.team_tag ? `@${post.team_tag.toLowerCase().replace(/[^a-z0-9]/g, '')}_cricket` : '@icc_matchreels'}
                                                                    </span>
                                                                    <span className="w-3.5 h-3.5 rounded-full bg-blue-500 text-white text-[9px] flex items-center justify-center font-bold">✓</span>
                                                                </div>
                                                                <span className="text-[10px] text-gray-500 font-medium">Instagram Reels • Audio Trending</span>
                                                            </div>
                                                        </div>
                                                        <a
                                                            href={post.insta_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-1 px-3 py-1 bg-gradient-to-r from-pink-600 via-rose-600 to-purple-600 hover:from-pink-700 hover:to-purple-700 text-white rounded-lg text-xs font-bold shadow-xs transition"
                                                        >
                                                            <span>Watch Reel</span>
                                                            <ExternalLink className="w-3 h-3" />
                                                        </a>
                                                    </div>

                                                    {/* Reel Video Player Card with Preview */}
                                                    <a
                                                        href={post.insta_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="block relative rounded-2xl overflow-hidden aspect-[9/10] sm:aspect-video bg-gray-900 group cursor-pointer shadow-inner"
                                                    >
                                                        <img
                                                            src={post.media_url || "https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=800&auto=format&fit=crop&q=80"}
                                                            alt="Instagram Reel Cover"
                                                            className="w-full h-full object-cover group-hover:scale-105 transition duration-300 opacity-85"
                                                        />
                                                        <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent flex flex-col justify-between p-4">
                                                            {/* Top Badge */}
                                                            <div className="flex justify-between items-center">
                                                                <span className="px-2.5 py-1 bg-black/60 backdrop-blur-md rounded-lg text-[11px] font-bold text-white flex items-center gap-1.5 border border-white/20">
                                                                    <span className="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                                                                    Instagram Reel
                                                                </span>
                                                                <span className="w-7 h-7 rounded-full bg-black/50 backdrop-blur-md flex items-center justify-center text-white/80">
                                                                    <Volume2 className="w-3.5 h-3.5" />
                                                                </span>
                                                            </div>

                                                            {/* Central Play Button */}
                                                            <div className="self-center">
                                                                <div className="w-14 h-14 rounded-full bg-white/95 text-rose-600 shadow-2xl flex items-center justify-center group-hover:scale-110 group-hover:bg-white transition duration-200">
                                                                    <Play className="w-6 h-6 fill-rose-600 ml-0.5" />
                                                                </div>
                                                            </div>

                                                            {/* Bottom Sound & Caption info */}
                                                            <div className="space-y-1 text-white">
                                                                <p className="text-xs font-semibold line-clamp-1 drop-shadow-sm">
                                                                    {post.content}
                                                                </p>
                                                                <div className="flex items-center justify-between text-[11px] text-white/80">
                                                                    <span className="flex items-center gap-1">
                                                                        <span>♫</span> Original Match Sound • Trending
                                                                    </span>
                                                                    <span className="font-bold text-pink-300 group-hover:underline">
                                                                        Tap to Play Reel ↗
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                            )}

                                            {/* Action Buttons: Like, Comment, WhatsApp Share */}
                                            <div className="flex items-center justify-between pt-3 border-t border-gray-100 text-xs">
                                                <button
                                                    type="button"
                                                    onClick={() => handleLikePost(post.id)}
                                                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-bold transition cursor-pointer ${
                                                        isLiked
                                                            ? 'bg-rose-50 text-rose-600'
                                                            : 'text-gray-500 hover:bg-gray-100 hover:text-rose-600'
                                                    }`}
                                                >
                                                    <Heart className={`w-4 h-4 ${isLiked ? 'fill-rose-600 text-rose-600' : ''}`} />
                                                    <span>{post.likes_count || 0} Likes</span>
                                                </button>

                                                <div className="flex items-center gap-2">
                                                    <a
                                                        href={`https://api.whatsapp.com/send?text=${encodeURIComponent(
                                                            `Check out this match buzz on LiveCricket:\n"${post.content}"\n${window.location.href}`
                                                        )}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-xl font-bold transition"
                                                        title="Share on WhatsApp"
                                                    >
                                                        <Share2 className="w-3.5 h-3.5" />
                                                        <span>Share</span>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                        </div>
                    )}
                </div>
            )}

            {/* ==================== TAB 4: MATCH INFO ==================== */}
            {activeTab === 'info' && (
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-6">
                    <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">Match Details</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8 text-sm">
                        <div className="flex justify-between py-2 border-b border-gray-50">
                            <span className="text-gray-500">Match</span>
                            <span className="font-bold text-gray-900">{match.name || match.title}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b border-gray-50">
                            <span className="text-gray-500">Format / Type</span>
                            <span className="font-bold text-gray-900">{match.matchType || 'T20'}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b border-gray-50">
                            <span className="text-gray-500">Venue</span>
                            <span className="font-bold text-gray-900">{match.venue || 'TBA'}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b border-gray-50">
                            <span className="text-gray-500">Toss</span>
                            <span className="font-bold text-gray-900">{match.status_text || 'TBA'}</span>
                        </div>
                        {match.dateTimeGMT && (
                            <div className="flex justify-between py-2 border-b border-gray-50">
                                <span className="text-gray-500">Date & Time</span>
                                <span className="font-bold text-gray-900">{match.dateTimeGMT} GMT</span>
                            </div>
                        )}
                        {match.teams && match.teams.length > 0 && (
                            <div className="flex justify-between py-2 border-b border-gray-50 md:col-span-2">
                                <span className="text-gray-500">Teams</span>
                                <span className="font-bold text-indigo-700">{match.teams.join(' vs ')}</span>
                            </div>
                        )}
                        {pom && pom.name && (
                            <div className="flex justify-between items-center py-2.5 border-b border-amber-100 bg-amber-50/50 px-3 rounded-xl md:col-span-2">
                                <span className="text-amber-800 font-bold flex items-center gap-1.5">
                                    <Trophy className="w-4 h-4 text-amber-600" />
                                    Player of the Match (Man of the Match)
                                </span>
                                <span className="font-black text-gray-900">
                                    {pom.name} {pom.stats ? `(${pom.stats})` : ''}
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* ==================== PLAYER CAREER PROFILE MODAL ==================== */}
            {selectedPlayer && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5">
                    <div 
                        className="fixed inset-0" 
                        onClick={closePlayerModal}
                    />
                    <div className="relative bg-white rounded-3xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden flex flex-col z-10 animate-in fade-in zoom-in-95 duration-150 border border-gray-100">
                        {/* Modal Header */}
                        <div className="px-6 py-4 bg-gradient-to-r from-gray-900 via-indigo-950 to-gray-900 text-white flex items-center justify-between">
                            <div className="flex items-center gap-2.5">
                                <div className="w-8 h-8 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-300">
                                    <User className="w-4 h-4" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-base leading-tight">Player Career Record</h3>
                                    <p className="text-xs text-indigo-200/80">{selectedPlayer.name}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={closePlayerModal}
                                className="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-gray-300 hover:text-white transition cursor-pointer"
                                title="Close"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Modal Content */}
                        <div className="overflow-y-auto p-5 sm:p-6 space-y-6">
                            {playerLoading && (
                                <div className="py-16 text-center space-y-3">
                                    <Loader2 className="w-9 h-9 text-indigo-600 animate-spin mx-auto" />
                                    <p className="text-sm font-semibold text-gray-800">Fetching official career records for {selectedPlayer.name}...</p>
                                    <p className="text-xs text-gray-400">Loading Test, ODI, T20 & IPL statistics</p>
                                </div>
                            )}

                            {playerError && (
                                <div className="py-12 text-center space-y-3">
                                    <div className="w-12 h-12 bg-rose-50 text-rose-600 rounded-full flex items-center justify-center mx-auto">
                                        <Info className="w-6 h-6" />
                                    </div>
                                    <h4 className="font-bold text-gray-900 text-base">{playerError}</h4>
                                    <p className="text-xs text-gray-500">We couldn't retrieve the career stats for this player.</p>
                                    <button
                                        type="button"
                                        onClick={() => handlePlayerClick(selectedPlayer.id, selectedPlayer.name)}
                                        className="mt-2 inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl hover:bg-indigo-700 transition cursor-pointer"
                                    >
                                        Try Again
                                    </button>
                                </div>
                            )}

                            {!playerLoading && !playerError && playerProfile && (
                                <div className="space-y-6">
                                    {/* Player Profile Card */}
                                    <div className="flex flex-col sm:flex-row items-center sm:items-start gap-5 p-5 bg-gradient-to-br from-indigo-50/70 via-white to-gray-50 rounded-2xl border border-indigo-100/60 shadow-xs">
                                        {/* Photo */}
                                        <div className="relative w-24 h-24 sm:w-28 sm:h-28 rounded-2xl overflow-hidden bg-indigo-100 border-2 border-white shadow-md shrink-0 flex items-center justify-center">
                                            {playerProfile.image ? (
                                                <img
                                                    src={playerProfile.image}
                                                    alt={playerProfile.name}
                                                    className="w-full h-full object-cover object-top"
                                                    onError={(e) => { e.currentTarget.style.display = 'none'; }}
                                                />
                                            ) : (
                                                <User className="w-12 h-12 text-indigo-300" />
                                            )}
                                        </div>

                                        {/* Info */}
                                        <div className="space-y-2 text-center sm:text-left flex-1 min-w-0">
                                            <h3 className="text-2xl font-black text-gray-900 tracking-tight">
                                                {playerProfile.name}
                                            </h3>

                                            {/* Badges */}
                                            <div className="flex flex-wrap items-center justify-center sm:justify-start gap-1.5 pt-1">
                                                {playerProfile.personal_info?.role && (
                                                    <span className="px-2.5 py-1 bg-indigo-600 text-white text-xs font-bold rounded-lg shadow-xs">
                                                        {playerProfile.personal_info.role}
                                                    </span>
                                                )}
                                                {playerProfile.personal_info?.batting_style && (
                                                    <span className="px-2.5 py-1 bg-white border border-gray-200 text-gray-700 text-xs font-medium rounded-lg">
                                                        Bat: {playerProfile.personal_info.batting_style}
                                                    </span>
                                                )}
                                                {playerProfile.personal_info?.bowling_style && (
                                                    <span className="px-2.5 py-1 bg-white border border-gray-200 text-gray-700 text-xs font-medium rounded-lg">
                                                        Bowl: {playerProfile.personal_info.bowling_style}
                                                    </span>
                                                )}
                                            </div>

                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-500 pt-2 border-t border-gray-200/60">
                                                {playerProfile.personal_info?.born && (
                                                    <div><strong className="text-gray-700">Born:</strong> {playerProfile.personal_info.born}</div>
                                                )}
                                                {playerProfile.personal_info?.birth_place && (
                                                    <div><strong className="text-gray-700">Birth Place:</strong> {playerProfile.personal_info.birth_place}</div>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Batting Career Summary */}
                                    {playerProfile.batting_career && playerProfile.batting_career.stats && playerProfile.batting_career.stats.length > 0 && (
                                        <div className="bg-white rounded-2xl border border-gray-100 shadow-xs overflow-hidden">
                                            <div className="px-5 py-3 bg-gray-50/80 border-b border-gray-100 flex items-center justify-between">
                                                <h4 className="font-bold text-gray-900 text-sm flex items-center gap-2">
                                                    <Trophy className="w-4 h-4 text-amber-500" />
                                                    Batting Career Summary
                                                </h4>
                                            </div>
                                            <div className="overflow-x-auto">
                                                <table className="min-w-full divide-y divide-gray-100 text-xs">
                                                    <thead className="bg-gray-50 font-bold text-gray-600">
                                                        <tr>
                                                            <th className="px-4 py-2.5 text-left">Stat</th>
                                                            {playerProfile.batting_career.formats.map((fmt, i) => (
                                                                <th key={i} className="px-4 py-2.5 text-right uppercase tracking-wider">{fmt}</th>
                                                            ))}
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-gray-100">
                                                        {playerProfile.batting_career.stats.map((row, idx) => (
                                                            <tr key={idx} className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/40'}>
                                                                <td className="px-4 py-2 font-semibold text-gray-700 whitespace-nowrap">{row.label}</td>
                                                                {row.values.map((val, vi) => (
                                                                    <td key={vi} className={`px-4 py-2 text-right font-medium ${row.label === 'Runs' || row.label === 'Avg' ? 'font-bold text-gray-900' : 'text-gray-600'}`}>
                                                                        {val || '-'}
                                                                    </td>
                                                                ))}
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}

                                    {/* Bowling Career Summary */}
                                    {playerProfile.bowling_career && playerProfile.bowling_career.stats && playerProfile.bowling_career.stats.length > 0 && (
                                        <div className="bg-white rounded-2xl border border-gray-100 shadow-xs overflow-hidden">
                                            <div className="px-5 py-3 bg-gray-50/80 border-b border-gray-100 flex items-center justify-between">
                                                <h4 className="font-bold text-gray-900 text-sm flex items-center gap-2">
                                                    <Shield className="w-4 h-4 text-indigo-500" />
                                                    Bowling Career Summary
                                                </h4>
                                            </div>
                                            <div className="overflow-x-auto">
                                                <table className="min-w-full divide-y divide-gray-100 text-xs">
                                                    <thead className="bg-gray-50 font-bold text-gray-600">
                                                        <tr>
                                                            <th className="px-4 py-2.5 text-left">Stat</th>
                                                            {playerProfile.bowling_career.formats.map((fmt, i) => (
                                                                <th key={i} className="px-4 py-2.5 text-right uppercase tracking-wider">{fmt}</th>
                                                            ))}
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-gray-100">
                                                        {playerProfile.bowling_career.stats.map((row, idx) => (
                                                            <tr key={idx} className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/40'}>
                                                                <td className="px-4 py-2 font-semibold text-gray-700 whitespace-nowrap">{row.label}</td>
                                                                {row.values.map((val, vi) => (
                                                                    <td key={vi} className={`px-4 py-2 text-right font-medium ${row.label === 'Wickets' || row.label === 'Econ' ? 'font-bold text-indigo-900' : 'text-gray-600'}`}>
                                                                        {val || '-'}
                                                                    </td>
                                                                ))}
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Modal Footer */}
                        <div className="px-6 py-3.5 bg-gray-50 border-t border-gray-100 flex justify-end">
                            <button
                                type="button"
                                onClick={closePlayerModal}
                                className="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs font-bold rounded-xl transition cursor-pointer"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
