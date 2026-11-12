import React, { useState, useEffect, useRef, useMemo } from 'react';
import AdSlot from './AdSlot';
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
    Volume2,
    Radio,
    Mic,
    ChevronDown,
    ChevronUp,
    ChevronRight,
    Layers
} from 'lucide-react';

// Ball-by-ball commentary is loaded live directly from Cricbuzz API

export default function MatchDetailApp({ initialMatch, initialScorecard, initialCommentary, matchId }) {
    const [match, setMatch] = useState(initialMatch || {});
    const [scorecard, setScorecard] = useState(initialScorecard || {});
    const [realCommentary, setRealCommentary] = useState(() => {
        if (initialCommentary && Array.isArray(initialCommentary.data)) {
            return initialCommentary.data;
        }
        return [];
    });
    const [miniscore, setMiniscore] = useState(() => initialCommentary?.miniscore || null);
    const [isCommentaryLoading, setIsCommentaryLoading] = useState(false);
    const [isScorecardLoading, setIsScorecardLoading] = useState(false);

    const getInitialTab = () => {
        if (typeof window !== 'undefined') {
            const params = new URLSearchParams(window.location.search);
            const t = params.get('tab');
            if (t === 'live' || t === 'commentary') return 'commentary';
            if (t === 'buzz' || t === 'info' || t === 'scorecard') return t;
            if (t === 'summary') return 'live';
        }
        return 'scorecard';
    };
    const [activeTab, setActiveTab] = useState(getInitialTab);
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

    // Ball-by-Ball Live Commentary State
    const [commentaryFilter, setCommentaryFilter] = useState('all'); // 'all', 'boundaries', 'wickets', 'notes'
    const [commLanguage, setCommLanguage] = useState('en'); // 'en' (English AI) or 'hi' (Hindi AI)
    const [selectedCommInning, setSelectedCommInning] = useState('live'); // 'live' or specific inning number/id
    const [selectedOverTab, setSelectedOverTab] = useState('all'); // 'all' or specific over key e.g. '102'
    const [expandedOvers, setExpandedOvers] = useState({}); // { [overKey]: boolean }
    const [newCommentaryText, setNewCommentaryText] = useState('');
    const [newCommentaryTag, setNewCommentaryTag] = useState('4 Runs 🏏');
    const [isPostingCommentary, setIsPostingCommentary] = useState(false);
    const [commentarySuccessMsg, setCommentarySuccessMsg] = useState(null);

    const handlePostCommentary = async (e) => {
        e.preventDefault();
        if (!newCommentaryText.trim() || isPostingCommentary) return;
        setIsPostingCommentary(true);
        try {
            const author = newPostName.trim() || 'Live Blogger';
            localStorage.setItem('cricket_fan_name', author);

            const res = await fetch(`/api/matches/${matchId}/posts`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    content: `[Commentary] ${newCommentaryText.trim()}`,
                    user_name: author,
                    team_tag: newCommentaryTag,
                    post_type: 'commentary'
                })
            });

            const data = await res.json();
            if (res.ok && data.data) {
                setPosts(prev => [data.data, ...prev]);
                setNewCommentaryText('');
                setCommentarySuccessMsg('Commentary posted live! ⚡');
                setTimeout(() => setCommentarySuccessMsg(null), 3000);
            }
        } catch (err) {
            console.error('Error posting commentary:', err);
        } finally {
            setIsPostingCommentary(false);
        }
    };

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

    const fetchCommentary = async (isSilent = false) => {
        if (!matchId) return;
        if (!isSilent) setIsCommentaryLoading(true);
        try {
            const res = await fetch(`/api/matches/${matchId}/commentary`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.status === 'success' && Array.isArray(json.data)) {
                    setRealCommentary(json.data);
                    if (json.miniscore) {
                        setMiniscore(json.miniscore);
                    }
                }
            }
        } catch (err) {
            console.error('Failed to fetch live commentary:', err);
        } finally {
            if (!isSilent) setIsCommentaryLoading(false);
        }
    };

    const fetchScorecard = async (isSilent = false) => {
        if (!matchId) return;
        if (!isSilent) setIsScorecardLoading(true);
        try {
            const res = await fetch(`/api/matches/${matchId}/scorecard`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json && (json.data || json.innings || json.status === 'success')) {
                    setScorecard(json);
                }
            }
        } catch (err) {
            console.error('Failed to fetch live scorecard:', err);
        } finally {
            if (!isSilent) setIsScorecardLoading(false);
        }
    };

    const fetchMatchScore = async () => {
        if (!matchId) return;
        try {
            const res = await fetch(`/api/matches/${matchId}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const updatedMatch = await res.json();
                setMatch(prev => ({ ...prev, ...updatedMatch }));
            }
        } catch (e) {
            // ignore
        }
    };

    // Live Ball-by-Ball Commentary Polling (Every 8 seconds)
    useEffect(() => {
        if (matchId && (!realCommentary || realCommentary.length === 0)) {
            fetchCommentary();
        }

        const pollTimer = setInterval(() => {
            fetchCommentary(true);
            const isMatchLive = match?.status === 'live' ||
                (match?.status_text && (match.status_text.toLowerCase().includes('need') || match.status_text.toLowerCase().includes('batting') || match.status_text.toLowerCase().includes('trail') || match.status_text.toLowerCase().includes('lead') || match.status_text.toLowerCase().includes('progress')));
            if (isMatchLive || activeTab === 'commentary') {
                fetchMatchScore();
            }
        }, 8000);

        return () => clearInterval(pollTimer);
    }, [matchId, match?.status, match?.status_text, activeTab]);

    // On mount or tab switch: fetch scorecard asynchronously so full innings data is always ready
    useEffect(() => {
        if (matchId) {
            const hasData = (scorecard?.data?.innings && scorecard.data.innings.length > 0) ||
                            (scorecard?.data?.batting && scorecard.data.batting.length > 0) ||
                            (scorecard?.innings && scorecard.innings.length > 0);
            if (!hasData) {
                fetchScorecard(true);
            }
        }
    }, [activeTab, matchId]);

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
            const [mRes, scRes, cRes] = await Promise.allSettled([
                fetch(`/api/matches/${matchId}`, { headers: { 'Accept': 'application/json' } }),
                fetch(`/api/matches/${matchId}/scorecard`, { headers: { 'Accept': 'application/json' } }),
                fetch(`/api/matches/${matchId}/commentary`, { headers: { 'Accept': 'application/json' } })
            ]);

            if (mRes.status === 'fulfilled' && mRes.value.ok) {
                const updatedMatch = await mRes.value.json();
                setMatch(updatedMatch);
            }
            if (scRes.status === 'fulfilled' && scRes.value.ok) {
                const updatedSc = await scRes.value.json();
                setScorecard(updatedSc);
            }
            if (cRes.status === 'fulfilled' && cRes.value.ok) {
                const updatedComm = await cRes.value.json();
                if (updatedComm.status === 'success' && Array.isArray(updatedComm.data)) {
                    setRealCommentary(updatedComm.data);
                    if (updatedComm.miniscore) setMiniscore(updatedComm.miniscore);
                }
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

    // Auto-detect if match has live scores or scorecard data
    const hasActiveScorecard = (innings && innings.length > 0) ||
        (scorecard?.data?.batting && scorecard.data.batting.length > 0) ||
        (match?.score && Array.isArray(match.score) && match.score.length > 0) ||
        (match?.current_batsmen && Array.isArray(match.current_batsmen) && match.current_batsmen.some(b => b.name && b.name !== 'score not found'));

    const effectiveStatus = (match.status === 'scheduled' && hasActiveScorecard) ? 'live' : (match.status || 'live');

    const effectiveStatusText = (() => {
        if (!match.status_text) return hasActiveScorecard ? 'Match in progress' : '';
        const lower = match.status_text.toLowerCase().trim();
        if (hasActiveScorecard && ['scheduled', 'match scheduled', 'upcoming'].includes(lower)) {
            const firstInn = innings[0];
            return firstInn?.team ? `${firstInn.team} batting (${firstInn.score || ''})` : 'Match in progress';
        }
        return match.status_text;
    })();

    // Detect return tab (from URL ?tab= or ?fromTab=, localStorage, or inferred from match status)
    const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
    const tabParam = urlParams?.get('tab') || urlParams?.get('fromTab');
    let fallbackTab = 'live';
    if (typeof window !== 'undefined') {
        try {
            const saved = localStorage.getItem('last_cricket_tab');
            if (['live', 'upcoming', 'recent'].includes(saved)) {
                fallbackTab = saved;
            }
        } catch (e) {}
    }
    if (effectiveStatus === 'completed' || effectiveStatus === 'abandoned') {
        fallbackTab = 'recent';
    } else if (effectiveStatus === 'scheduled') {
        fallbackTab = 'upcoming';
    } else {
        fallbackTab = 'live';
    }
    const returnTab = (tabParam && ['live', 'upcoming', 'recent'].includes(tabParam) && !hasActiveScorecard) ? tabParam : fallbackTab;
    const backUrl = `/live-scores?tab=${returnTab}`;

    // 1. Fan Posts formatted as community commentary
    const fanCommentaryList = useMemo(() => {
        return (posts || [])
            .filter(p => p.post_type === 'commentary' || (p.content && p.content.startsWith('[Commentary]')))
            .map(cp => {
                const rawContent = cp.content ? cp.content.replace(/^\[Commentary\]\s*/, '') : '';
                return {
                    id: `fan-${cp.id}`,
                    over: 'FAN',
                    badge: '💬',
                    badgeType: 'expert',
                    title: `${cp.user_name || 'Fan'} (Community):`,
                    desc: rawContent,
                    type: 'fan_update',
                    tag: cp.team_tag || 'Live Take',
                    time: cp.created_at ? new Date(cp.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Just now',
                    timestamp: new Date(cp.created_at || Date.now()).getTime()
                };
            });
    }, [posts]);

    // 2. Real Cricbuzz Live Commentary + Fan commentary combined
    const allCommentaryList = useMemo(() => {
        if (realCommentary && realCommentary.length > 0) {
            return [...fanCommentaryList, ...realCommentary];
        }

        const list = [...fanCommentaryList];
        if (match?.status_text) {
            list.push({
                id: 'match-status-banner',
                over: 'RESULT',
                badge: '🏆',
                badgeType: 'expert',
                title: `Match Status: ${match.status_text}`,
                desc: `Current match state between ${match?.team1_name || match?.teams?.[0] || 'Team 1'} and ${match?.team2_name || match?.teams?.[1] || 'Team 2'}.`,
                type: 'result',
                tag: 'Match Status',
                time: 'Live'
            });
        }
        return list;
    }, [realCommentary, fanCommentaryList, match]);

    // 2.5 Inning Detection: Identify separate innings for multi-day, test & limited-overs matches
    const availableCommentaryInnings = useMemo(() => {
        // Source 1: Scorecard innings
        const scInns = scorecard?.data?.innings || scorecard?.innings || [];
        if (scInns.length > 0) {
            return scInns.map((inn, idx) => ({
                id: inn.id ? String(inn.id) : String(idx + 1),
                num: idx + 1,
                label: `${inn.short_name || inn.team || `Innings ${idx + 1}`} (${inn.score || ''})`,
                team: inn.short_name || inn.team || `Team ${idx + 1}`,
                score: inn.score,
                isLive: miniscore?.inningsId ? String(inn.id) === String(miniscore.inningsId) : idx === scInns.length - 1
            }));
        }

        // Source 2: Distinct innings from commentary deliveries
        const innSet = new Set();
        allCommentaryList.forEach(item => {
            const inn = item.inning || item.inningsId;
            if (inn) innSet.add(String(inn));
        });

        if (innSet.size > 1) {
            return Array.from(innSet).sort((a, b) => parseInt(a, 10) - parseInt(b, 10)).map(id => {
                const isSecond = id === '2';
                const teamName = isSecond ? (match?.team2_name || match?.teams?.[1] || '2nd Innings') : (match?.team1_name || match?.teams?.[0] || '1st Innings');
                const scoreStr = isSecond ? (match?.team2_score || '') : (match?.team1_score || '');
                return {
                    id: String(id),
                    num: parseInt(id, 10) || 1,
                    label: scoreStr ? `${teamName} (${scoreStr})` : `${teamName}`,
                    team: teamName,
                    score: scoreStr,
                    isLive: miniscore?.inningsId ? String(miniscore.inningsId) === String(id) : isSecond
                };
            });
        }

        // Source 3: Fallback from match scores / teams
        const t1Score = match?.team1_score || match?.scores?.find(s => s.inning === 1)?.score;
        const t2Score = match?.team2_score || match?.scores?.find(s => s.inning === 2)?.score;
        const t1Name = match?.team1_name || match?.teams?.[0] || '1st Innings';
        const t2Name = match?.team2_name || match?.teams?.[1] || '2nd Innings';

        if (t2Score || (miniscore?.inningsId && String(miniscore.inningsId) === '2')) {
            return [
                {
                    id: '1',
                    num: 1,
                    label: t1Score ? `${t1Name} (${t1Score})` : `${t1Name} (1st Innings)`,
                    team: t1Name,
                    score: t1Score,
                    isLive: false
                },
                {
                    id: '2',
                    num: 2,
                    label: t2Score ? `${t2Name} (${t2Score})` : `${t2Name} (2nd Innings)`,
                    team: t2Name,
                    score: t2Score,
                    isLive: true
                }
            ];
        }

        if (t1Name && (t1Score || match?.status === 'live')) {
            return [
                {
                    id: '1',
                    num: 1,
                    label: t1Score ? `${t1Name} (${t1Score})` : `${t1Name}`,
                    team: t1Name,
                    score: t1Score,
                    isLive: true
                }
            ];
        }

        return [];
    }, [scorecard, allCommentaryList, miniscore, match]);

    // Active commentary inning target
    const activeCommentaryInningId = useMemo(() => {
        if (!availableCommentaryInnings || availableCommentaryInnings.length <= 1) return null;
        if (selectedCommInning !== 'live') return String(selectedCommInning);
        const liveInn = availableCommentaryInnings.find(i => i.isLive) || availableCommentaryInnings[availableCommentaryInnings.length - 1];
        return liveInn ? String(liveInn.id) : null;
    }, [availableCommentaryInnings, selectedCommInning]);

    // Inning-filtered commentary list
    const inningCommentaryList = useMemo(() => {
        if (!activeCommentaryInningId) return allCommentaryList;
        return allCommentaryList.filter(item => {
            const itemInn = String(item.inning || item.inningsId || '');
            if (!itemInn) return true; // keep general match announcements & notes
            return itemInn === activeCommentaryInningId;
        });
    }, [allCommentaryList, activeCommentaryInningId]);

    // 3. Filtered Commentary based on user selection: all / balls / boundaries / wickets / notes
    const filteredCommentaryList = useMemo(() => {
        return inningCommentaryList.filter(item => {
            if (commentaryFilter === 'balls') {
                return item.type === 'ball';
            }
            if (commentaryFilter === 'boundaries') {
                return item.type === 'ball' && (item.badge === '4' || item.badge === '6' || item.badgeType === 'four' || item.badgeType === 'six');
            }
            if (commentaryFilter === 'wickets') {
                return item.type === 'ball' && (item.badge === 'W' || item.badgeType === 'wicket');
            }
            if (commentaryFilter === 'notes') {
                return item.type !== 'ball';
            }
            return true;
        });
    }, [inningCommentaryList, commentaryFilter]);

    // Helper to extract integer over from any commentary item
    const extractItemOverNumber = (item) => {
        if (!item) return null;
        if (typeof item.overNumber === 'number' && !isNaN(item.overNumber)) {
            return item.overNumber;
        }
        if (typeof item.overNumber === 'string' && !isNaN(parseInt(item.overNumber, 10))) {
            return parseInt(item.overNumber, 10);
        }
        if (typeof item.over === 'number' && !isNaN(item.over)) {
            return Math.floor(item.over);
        }
        if (typeof item.over === 'string') {
            const m = item.over.match(/(\d+)(?:\.\d+)?/);
            if (m) return parseInt(m[1], 10);
        }
        if (typeof item.time === 'string') {
            const m = item.time.match(/(\d+)(?:\.\d+)?/);
            if (m) return parseInt(m[1], 10);
        }
        if (typeof item.title === 'string') {
            const m = item.title.match(/(\d+)\.\d+/);
            if (m) return parseInt(m[1], 10);
        }
        return null;
    };

    // 4. Over Grouping: Group commentary entries by Over number
    const { overGroups, numericOverGroups, hasNumericOvers } = useMemo(() => {
        if (!filteredCommentaryList || filteredCommentaryList.length === 0) {
            return { overGroups: [], numericOverGroups: [], hasNumericOvers: false };
        }

        // Pass 1: directly extract over for each item
        const mapped = filteredCommentaryList.map(item => ({
            item,
            over: extractItemOverNumber(item)
        }));

        // Pass 2: fill null overs (such as notes/quotes) from the nearest delivery in the stream
        for (let i = 0; i < mapped.length; i++) {
            if (mapped[i].over === null) {
                // Look ahead in descending list
                for (let j = i + 1; j < mapped.length; j++) {
                    if (mapped[j].over !== null) {
                        mapped[i].over = mapped[j].over;
                        break;
                    }
                }
                // If still null, look behind
                if (mapped[i].over === null) {
                    for (let j = i - 1; j >= 0; j--) {
                        if (mapped[j].over !== null) {
                            mapped[i].over = mapped[j].over;
                            break;
                        }
                    }
                }
            }
        }

        // Pass 3: assemble groups
        const groupsMap = new Map();
        const groupOrder = [];

        mapped.forEach(({ item, over }) => {
            const groupKey = over !== null ? String(over) : 'general';
            if (!groupsMap.has(groupKey)) {
                const isNumeric = over !== null;
                const newGroup = {
                    key: groupKey,
                    overNumber: over,
                    isNumeric,
                    title: isNumeric ? `Over ${over}` : 'Match Notes & Updates',
                    items: [],
                    runs: 0,
                    wickets: 0,
                    bowler: null,
                    isCurrent: false,
                };
                groupsMap.set(groupKey, newGroup);
                groupOrder.push(groupKey);
            }
            const grp = groupsMap.get(groupKey);
            grp.items.push(item);

            if (item.type === 'over-separator') {
                if (item.overRuns !== undefined) grp.runs = item.overRuns;
                if (item.desc && item.desc.includes('Bowler:')) {
                    const bm = item.desc.match(/Bowler:\s*([^(|]+)/);
                    if (bm) grp.bowler = bm[1].trim();
                }
            } else if (item.type === 'ball') {
                if (item.badge === 'W' || item.badgeType === 'wicket') {
                    grp.wickets += 1;
                }
                const r = parseInt(item.badge, 10);
                if (!isNaN(r)) grp.runs += r;
                if (!grp.bowler && item.title && item.title.includes(' to ')) {
                    grp.bowler = item.title.split(' to ')[0].trim();
                }
            }
        });

        const allGroups = groupOrder.map(k => groupsMap.get(k));

        const getItemSortScore = (item) => {
            if (item.type === 'over-separator') {
                return 9999999999;
            }
            if (typeof item.ballMetric === 'number' && !isNaN(item.ballMetric)) {
                return item.ballMetric;
            }
            if (typeof item.over === 'number' && !isNaN(item.over)) {
                return item.over;
            }
            if (typeof item.over === 'string') {
                const m = item.over.match(/(\d+\.\d+)/);
                if (m) return parseFloat(m[1]);
            }
            if (typeof item.title === 'string') {
                const m = item.title.match(/(\d+\.\d+)/);
                if (m) return parseFloat(m[1]);
            }
            if (typeof item.timestamp === 'number') {
                return item.timestamp / 10000000000;
            }
            return 0;
        };

        // Sort items inside each numeric over in reverse chronological order (latest ball on top: 6, 5, 4, 3, 2, 1)
        allGroups.forEach(grp => {
            if (grp.isNumeric && grp.items.length > 1) {
                grp.items.sort((a, b) => {
                    const scoreA = getItemSortScore(a);
                    const scoreB = getItemSortScore(b);
                    return scoreB - scoreA; // Latest ball on top!
                });
            }
        });
        const numGroups = allGroups.filter(g => g.isNumeric);
        const isLiveInning = selectedCommInning === 'live' || (availableCommentaryInnings.find(i => String(i.id) === String(selectedCommInning))?.isLive);
        if (numGroups.length > 0 && effectiveStatus === 'live' && isLiveInning) {
            numGroups[0].isCurrent = true;
        }

        return {
            overGroups: allGroups,
            numericOverGroups: numGroups,
            hasNumericOvers: numGroups.length > 0
        };
    }, [filteredCommentaryList, selectedCommInning, availableCommentaryInnings, effectiveStatus]);

    const isOverExpanded = (grp) => {
        if (selectedOverTab !== 'all' && selectedOverTab === grp.key) {
            return true;
        }
        if (expandedOvers[grp.key] !== undefined) {
            return expandedOvers[grp.key];
        }
        return grp.isCurrent;
    };

    const toggleOver = (key) => {
        setExpandedOvers(prev => {
            const currentVal = prev[key] !== undefined
                ? prev[key]
                : (overGroups.find(g => g.key === key)?.isCurrent ?? false);
            return { ...prev, [key]: !currentVal };
        });
    };

    const expandAllOvers = () => {
        const next = {};
        overGroups.forEach(g => { next[g.key] = true; });
        setExpandedOvers(next);
    };

    const collapseAllOvers = () => {
        const next = {};
        overGroups.forEach(g => { next[g.key] = false; });
        setExpandedOvers(next);
    };

    const renderCommentaryCard = (item, idx, showInfeedAd) => {
        // Custom Presentation / Quote Card
        if (item.type === 'interview') {
            return (
                <React.Fragment key={item.id || idx}>
                    <div className="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-amber-500/10 via-slate-900 to-indigo-950/40 border border-amber-500/30 shadow-md flex items-start gap-3 sm:gap-4">
                        <div className="shrink-0 text-center w-12 sm:w-14">
                            <span className="px-2 py-1 rounded-lg bg-amber-500/20 border border-amber-500/40 font-mono font-black text-xs text-amber-300 block">
                                QUOTE
                            </span>
                            <span className="text-[9px] text-amber-400/80 mt-1 block truncate font-semibold">
                                Speech
                            </span>
                        </div>
                        <div className="shrink-0 w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center bg-amber-500/20 border border-amber-500/40 text-amber-300 text-base shadow-sm">
                            🎙️
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="flex flex-wrap items-center justify-between gap-1.5">
                                <h5 className="text-xs sm:text-sm font-black text-amber-200 flex items-center gap-1.5">
                                    <span>🎙️</span>
                                    <span>{item.title}</span>
                                </h5>
                                <span className="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 text-[10px] font-bold border border-amber-500/30 shrink-0">
                                    {item.tag || 'Post-Match Quote'}
                                </span>
                            </div>
                            <p className="text-xs sm:text-sm text-slate-200 mt-1.5 leading-relaxed font-sans italic border-l-2 border-amber-500/40 pl-3">
                                “{item.desc}”
                            </p>
                        </div>
                    </div>
                    {showInfeedAd && (
                        <AdSlot type="infeed" slotId={`infeed-comm-${idx}`} />
                    )}
                </React.Fragment>
            );
        }

        // Custom Match Wrap-up Card
        if (item.type === 'match_wrap') {
            return (
                <React.Fragment key={item.id || idx}>
                    <div className="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-emerald-500/10 via-slate-900 to-slate-900 border border-emerald-500/30 shadow-md flex items-start gap-3 sm:gap-4">
                        <div className="shrink-0 text-center w-12 sm:w-14">
                            <span className="px-2 py-1 rounded-lg bg-emerald-500/20 border border-emerald-500/40 font-mono font-black text-xs text-emerald-300 block">
                                FINAL
                            </span>
                            <span className="text-[9px] text-emerald-400/80 mt-1 block truncate font-semibold">
                                Result
                            </span>
                        </div>
                        <div className="shrink-0 w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-base shadow-sm">
                            🏆
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="flex flex-wrap items-center justify-between gap-1.5">
                                <h5 className="text-xs sm:text-sm font-black text-emerald-200 flex items-center gap-1.5">
                                    <span>🏆</span>
                                    <span>{item.title}</span>
                                </h5>
                                <span className="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px] font-bold border border-emerald-500/30 shrink-0">
                                    {item.tag || 'Match Conclusion'}
                                </span>
                            </div>
                            <p className="text-xs sm:text-sm text-slate-300 mt-1 leading-relaxed font-sans">
                                {item.desc}
                            </p>
                        </div>
                    </div>
                    {showInfeedAd && (
                        <AdSlot type="infeed" slotId={`infeed-comm-${idx}`} />
                    )}
                </React.Fragment>
            );
        }

        // Regular Deliveries, Over Separators, and Notes
        return (
            <React.Fragment key={item.id || idx}>
                <div className="p-4 sm:p-5 rounded-2xl bg-white/5 border border-white/5 hover:border-white/10 transition flex items-start gap-3 sm:gap-4">
                    {/* Over Badge */}
                    <div className="shrink-0 text-center w-12 sm:w-14">
                        <span className="px-2 py-1 rounded-lg bg-black/50 border border-white/10 font-mono font-bold text-xs sm:text-sm text-slate-200 block">
                            {item.over}
                        </span>
                        <span className="text-[9px] text-slate-500 mt-1 block truncate">
                            {item.time}
                        </span>
                    </div>

                    {/* Badge */}
                    <div
                        className={`shrink-0 w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center font-mono font-black text-xs sm:text-sm shadow-md ${
                            item.badgeType === 'wicket' || item.badge === 'W'
                                ? 'bg-rose-600 text-white shadow-rose-600/40 animate-pulse'
                                : item.badgeType === 'six' || item.badge === '6'
                                ? 'bg-purple-600 text-white shadow-purple-600/40'
                                : item.badgeType === 'four' || item.badge === '4'
                                ? 'bg-blue-600 text-white shadow-blue-600/40'
                                : item.badgeType === 'extra' || item.badge === 'Wd' || item.badge === 'Nb'
                                ? 'bg-amber-600 text-white shadow-amber-600/40'
                                : item.badgeType === 'milestone'
                                ? 'bg-amber-500 text-slate-950 shadow-amber-500/40 font-black'
                                : item.badgeType === 'expert'
                                ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold'
                                : item.badgeType === 'dot' || item.badge === '0'
                                ? 'bg-slate-800 text-slate-300 border border-white/10'
                                : 'bg-emerald-600/30 text-emerald-300 border border-emerald-500/30'
                        }`}
                    >
                        <span>{item.badge}</span>
                    </div>

                    {/* Prose */}
                    <div className="flex-1 min-w-0">
                        <div className="flex flex-wrap items-center justify-between gap-1.5">
                            <h5 className="text-xs sm:text-sm font-black text-white">{item.title}</h5>
                            {item.tag && (
                                <span className="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 text-[10px] font-bold border border-amber-500/30 shrink-0">
                                    {item.tag}
                                </span>
                            )}
                        </div>
                        <p className="text-xs sm:text-sm text-slate-200 mt-1 leading-relaxed font-sans">
                            {commLanguage === 'hi' ? (item.ai_hindi || item.desc) : (item.desc || item.rawText)}
                        </p>
                    </div>
                </div>
                {showInfeedAd && (
                    <AdSlot type="infeed" slotId={`infeed-comm-${idx}`} />
                )}
            </React.Fragment>
        );
    };

    const liveCreaseBatTeam = miniscore?.batTeamScoreObj?.teamName || miniscore?.batTeamDetails?.teamName || match?.teams?.[0] || 'Batting';
    const liveCreaseScore = (() => {
        if (!miniscore) return '';
        if (miniscore.batTeamScoreObj?.teamScore) return miniscore.batTeamScoreObj.teamScore;
        if (miniscore.batTeamScoreObj?.score) return miniscore.batTeamScoreObj.score;
        if (miniscore.batTeamDetails?.teamScore) return miniscore.batTeamDetails.teamScore;
        if (miniscore.batTeamDetails?.score) return miniscore.batTeamDetails.score;

        if (innings && innings.length > 0) {
            const matched = innings.find(i =>
                (miniscore.inningsId && String(i.id) === String(miniscore.inningsId)) ||
                (i.short_name && liveCreaseBatTeam && i.short_name.toLowerCase() === liveCreaseBatTeam.toLowerCase()) ||
                (i.team && liveCreaseBatTeam && i.team.toLowerCase().includes(liveCreaseBatTeam.toLowerCase()))
            ) || innings[innings.length - 1];
            if (matched?.score) return matched.score;
            if (matched?.runs !== undefined && matched?.wickets !== undefined) return `${matched.runs}/${matched.wickets}`;
        }

        if (match?.score && Array.isArray(match.score)) {
            const matched = match.score.find(s =>
                (s.batting_team && liveCreaseBatTeam && s.batting_team.toLowerCase().includes(liveCreaseBatTeam.toLowerCase())) ||
                (s.inning && liveCreaseBatTeam && s.inning.toLowerCase().includes(liveCreaseBatTeam.toLowerCase()))
            ) || match.score[match.score.length - 1];
            if (matched?.display_score) return matched.display_score;
            if (matched?.r !== undefined && matched?.w !== undefined) return `${matched.r}/${matched.w}`;
        }

        return '';
    })();

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
            {/* Top Navigation & Actions */}
            <div className="flex items-center justify-between">
                <a
                    href={backUrl}
                    onClick={(e) => {
                        if (typeof window !== 'undefined' && window.history.length > 1 && document.referrer && document.referrer.includes('/live-scores')) {
                            e.preventDefault();
                            window.history.back();
                        }
                    }}
                    className="inline-flex items-center text-sm font-bold text-indigo-400 hover:text-indigo-300 transition"
                >
                    <ArrowLeft className="w-4 h-4 mr-1.5" /> Back to Live Scores
                </a>
                <div className="flex items-center gap-3">
                    {lastRefreshMsg && (
                        <span className="text-xs text-emerald-400 font-bold">{lastRefreshMsg}</span>
                    )}
                    <button
                        type="button"
                        onClick={handleRefresh}
                        disabled={isRefreshing}
                        className="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white rounded-xl text-xs font-black shadow-lg shadow-indigo-600/30 transition disabled:opacity-50 transform active:scale-95"
                    >
                        <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
                        {isRefreshing ? 'Refreshing...' : 'Refresh Score'}
                    </button>
                </div>
            </div>

            {/* Match Header Hero Card */}
            <div className="bg-[#131b2e] rounded-3xl shadow-2xl border border-white/10 p-6 md:p-8 space-y-4">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="px-2.5 py-1 rounded-lg text-xs font-black uppercase tracking-wider bg-white/5 border border-white/10 text-slate-300">
                        {match.matchType || 'Match'}
                    </span>
                    {match.season && (
                        <span className="px-2.5 py-1 rounded-lg text-xs font-medium bg-white/5 text-slate-400">
                            Season {match.season}
                        </span>
                    )}
                    {effectiveStatus && (
                        <span className={`px-2.5 py-1 rounded-lg text-xs font-black uppercase tracking-wider ${
                            effectiveStatus === 'live'
                                ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse'
                                : (effectiveStatus === 'completed' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-blue-500/20 text-blue-300 border border-blue-500/40')
                        }`}>
                            {effectiveStatus}
                        </span>
                    )}
                </div>

                <h1 className="text-2xl md:text-3xl font-black text-white leading-tight">
                    {match.name || match.title || 'Match Details'}
                </h1>

                <div className="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                    <span className="flex items-center">
                        <MapPin className="w-4 h-4 mr-1 text-slate-500" />
                        {match.venue || 'Venue TBA'}
                    </span>
                    {match.dateTimeGMT && (
                        <span className="flex items-center">
                            <Calendar className="w-4 h-4 mr-1 text-slate-500" />
                            {match.dateTimeGMT} GMT
                        </span>
                    )}
                </div>

                {/* Status / Outcome Banner */}
                {effectiveStatusText && (
                    <div className={`p-3.5 rounded-2xl text-sm font-bold flex items-center gap-2 border ${
                        effectiveStatus === 'live'
                            ? 'bg-rose-500/15 border-rose-500/30 text-rose-300'
                            : (effectiveStatus === 'completed' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300' : 'bg-amber-500/15 border-amber-500/30 text-amber-300')
                    }`}>
                        <Shield className={`w-4 h-4 shrink-0 ${
                            effectiveStatus === 'live' ? 'text-rose-400' : (effectiveStatus === 'completed' ? 'text-emerald-400' : 'text-amber-400')
                        }`} />
                        <span>{effectiveStatusText}</span>
                    </div>
                )}

                {/* Always-Visible Live Scorecard Summary Card */}
                {((innings && innings.length > 0) || (match.score && match.score.length > 0)) && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        {innings && innings.length > 0 ? (
                            innings.map((inn, idx) => {
                                const isCurrentlyBatting = inn.is_batting || (idx === 0 && effectiveStatus === 'live');
                                return (
                                    <div key={idx} className="p-4 rounded-2xl bg-[#0b101e]/90 border border-white/10 flex items-center justify-between shadow-lg">
                                        <div className="flex items-center gap-3 min-w-0">
                                            <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-600 text-white font-black text-sm flex items-center justify-center shadow-md shrink-0">
                                                {String(inn.team || 'T').substring(0, 2).toUpperCase()}
                                            </div>
                                            <div className="min-w-0">
                                                <h3 className="font-black text-white text-base truncate flex items-center gap-2">
                                                    <span>{inn.team || `Innings ${idx + 1}`}</span>
                                                    {isCurrentlyBatting && (
                                                        <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                                    )}
                                                </h3>
                                                <p className="text-xs text-slate-400 font-mono mt-0.5">
                                                    RR: <span className="text-slate-300 font-bold">{inn.run_rate || 'N/A'}</span>
                                                    {inn.overs ? ` • ${inn.overs} Overs` : ''}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="text-right shrink-0">
                                            <span className="text-2xl font-black font-mono text-amber-300 tracking-tight">
                                                {inn.score || '0/0'}
                                            </span>
                                            {inn.overs && (
                                                <span className="block text-xs font-mono text-slate-400">
                                                    ({inn.overs} ov)
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            match.score?.map((s, idx) => {
                                const sRuns = s.r || s.runs || 0;
                                let sWickets = s.w ?? s.wickets ?? 0;
                                const isMatchFinished = effectiveStatus === 'completed' || (match.matchEnded ?? false) || (match.status_text || '').toLowerCase().includes('won by');
                                if (isMatchFinished && idx === 1 && sWickets === 0 && (match.status_text || '').toLowerCase().includes('won by') && (match.status_text || '').toLowerCase().includes('run')) {
                                    sWickets = 10;
                                }
                                const sDisplay = (s.display_score && s.display_score !== `${sRuns}/0`) ? s.display_score : (sWickets === 10 ? `${sRuns}/10` : `${sRuns}/${sWickets}`);
                                return (
                                    <div key={idx} className="p-4 rounded-2xl bg-[#0b101e]/90 border border-white/10 flex items-center justify-between shadow-lg">
                                        <span className="font-black text-white text-base">{s.batting_team || s.inning || `Innings ${idx + 1}`}</span>
                                        <span className="text-2xl font-black font-mono text-amber-300">{sDisplay}</span>
                                    </div>
                                );
                            })
                        )}
                    </div>
                )}

                {/* Player of the Match / Man of the Match Hero Banner */}
                {pom && pom.name && (
                    <div className="p-4 bg-gradient-to-r from-amber-500/20 via-amber-400/10 to-yellow-500/5 border border-amber-500/30 rounded-2xl flex items-center justify-between gap-4 shadow-xl">
                        <div className="flex items-center gap-3.5 min-w-0">
                            <div className="relative shrink-0">
                                {pom.image ? (
                                    <img
                                        src={pom.image}
                                        alt={pom.name}
                                        className="w-13 h-13 rounded-full object-cover border-2 border-amber-400 shadow-md"
                                        onError={(e) => { e.target.style.display = 'none'; }}
                                    />
                                ) : (
                                    <div className="w-13 h-13 rounded-full bg-amber-500/20 border-2 border-amber-400 flex items-center justify-center text-amber-300 font-bold">
                                        <User className="w-6 h-6" />
                                    </div>
                                )}
                                <span className="absolute -bottom-1 -right-1 p-1 bg-amber-500 text-slate-950 rounded-full shadow-xs">
                                    <Trophy className="w-3 h-3" />
                                </span>
                            </div>
                            <div className="min-w-0">
                                <div className="flex items-center gap-1.5">
                                    <span className="text-[11px] font-black uppercase tracking-wider text-amber-300 bg-amber-500/20 border border-amber-500/30 px-2.5 py-0.5 rounded-full inline-flex items-center gap-1">
                                        <Trophy className="w-3 h-3 text-amber-400" />
                                        {pom.award || 'Player of the Match'}
                                    </span>
                                </div>
                                <h3 className="text-base sm:text-lg font-black text-white truncate mt-1">
                                    {pom.name}
                                </h3>
                                {pom.stats && (
                                    <p className="text-xs font-semibold text-slate-300 mt-0.5">
                                        Performance: <span className="text-amber-300 font-black">{pom.stats}</span>
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="hidden sm:flex items-center gap-2 text-amber-300 font-black text-xs bg-amber-500/20 px-3.5 py-2 rounded-xl border border-amber-500/30 shrink-0">
                            <Trophy className="w-4 h-4 text-amber-400" />
                            <span>Man of the Match</span>
                        </div>
                    </div>
                )}
            </div>

            {/* Google AdSense / Sponsor Leaderboard Banner */}
            <AdSlot type="leaderboard" slotId="match-detail-leaderboard" />

            {/* Cricbuzz Style Navigation Tabs */}
            <div className="border-b border-white/10 bg-[#111827]/90 rounded-2xl px-6 pt-3 shadow-xl">
                <nav className="flex space-x-8" aria-label="Tabs">
                    <button
                        type="button"
                        onClick={() => setActiveTab('scorecard')}
                        className={`inline-flex items-center py-4 px-1 border-b-2 font-black text-sm transition ${
                            activeTab === 'scorecard'
                                ? 'border-indigo-500 text-indigo-400'
                                : 'border-transparent text-slate-400 hover:text-white hover:border-slate-500'
                        }`}
                    >
                        <FileText className="w-4 h-4 mr-2" />
                        Full Scorecard
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('commentary')}
                        className={`inline-flex items-center py-4 px-1 border-b-2 font-black text-sm transition ${
                            activeTab === 'commentary'
                                ? 'border-amber-500 text-amber-400'
                                : 'border-transparent text-slate-400 hover:text-white hover:border-slate-500'
                        }`}
                    >
                        <Radio className="w-4 h-4 mr-2 text-amber-400 animate-pulse" />
                        🎙️ Ball-by-Ball Commentary
                        <span className="ml-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            {allCommentaryList.length}
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('live')}
                        className={`inline-flex items-center py-4 px-1 border-b-2 font-black text-sm transition ${
                            activeTab === 'live'
                                ? 'border-indigo-500 text-indigo-400'
                                : 'border-transparent text-slate-400 hover:text-white hover:border-slate-500'
                        }`}
                    >
                        <Activity className="w-4 h-4 mr-2" />
                        Live Summary
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('buzz')}
                        className={`inline-flex items-center py-4 px-1 border-b-2 font-black text-sm transition relative ${
                            activeTab === 'buzz'
                                ? 'border-rose-500 text-rose-400'
                                : 'border-transparent text-slate-400 hover:text-white hover:border-slate-500'
                        }`}
                    >
                        <Flame className={`w-4 h-4 mr-1.5 ${activeTab === 'buzz' ? 'text-rose-500 fill-rose-500' : 'text-rose-400'}`} />
                        Fan Buzz & Reels
                        {posts.length > 0 && (
                            <span className="ml-1.5 px-2 py-0.5 rounded-full text-[11px] font-black bg-rose-500 text-white">
                                {posts.length}
                            </span>
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('info')}
                        className={`inline-flex items-center py-4 px-1 border-b-2 font-black text-sm transition ${
                            activeTab === 'info'
                                ? 'border-indigo-500 text-indigo-400'
                                : 'border-transparent text-slate-400 hover:text-white hover:border-slate-500'
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
                                        className={`px-4 py-2.5 rounded-xl font-black text-xs transition flex items-center gap-2 border ${
                                            selectedInningIdx === idx
                                                ? 'bg-gradient-to-r from-indigo-600 to-blue-600 text-white border-transparent shadow-lg shadow-indigo-600/30'
                                                : 'bg-[#131b2e] text-slate-300 hover:bg-white/10 hover:text-white border-white/10'
                                        }`}
                                    >
                                        <span>{inn.team || `Innings ${idx + 1}`}</span>
                                        <span className={`px-2 py-0.5 rounded-md text-[11px] font-black ${
                                            selectedInningIdx === idx ? 'bg-black/30 text-white' : 'bg-white/10 text-slate-300'
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
                            {currentBatting.length === 0 && currentBowling.length === 0 && (
                                isScorecardLoading ? (
                                    <div className="bg-white rounded-3xl p-12 text-center shadow-sm border border-gray-100 space-y-4">
                                        <Loader2 className="w-8 h-8 mx-auto text-indigo-600 animate-spin" />
                                        <p className="text-base font-bold text-gray-800">Loading full scorecard...</p>
                                        <p className="text-xs text-gray-500">Fetching player innings and bowling statistics...</p>
                                    </div>
                                ) : (
                                    <div className="bg-white rounded-3xl p-12 text-center shadow-sm border border-gray-100 space-y-2">
                                        <FileText className="w-8 h-8 mx-auto text-gray-300" />
                                        <p className="text-base font-bold text-gray-700">Detailed Scorecard Pending</p>
                                        <p className="text-xs text-gray-500">Full scorecard figures will appear here once official innings records are recorded.</p>
                                    </div>
                                )
                            )}
                        </div>
                    )}
                </div>
            )}

            {/* ==================== TAB: BALL-BY-BALL COMMENTARY ==================== */}
            {activeTab === 'commentary' && (
                <div className="space-y-6">
                    {/* Commentary Hero Header */}
                    <div className="bg-gradient-to-r from-amber-600 via-orange-600 to-indigo-700 rounded-3xl p-6 md:p-8 text-white shadow-xl relative overflow-hidden">
                        <div className="relative z-10 space-y-2 max-w-2xl">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 backdrop-blur-xs text-xs font-bold text-white uppercase tracking-wider">
                                <Radio className="w-3.5 h-3.5 animate-pulse text-amber-300" />
                                CricApp Live Engine
                            </div>
                            <h2 className="text-2xl md:text-3xl font-black tracking-tight">
                                Ball-by-Ball Live Commentary
                            </h2>
                            <p className="text-xs md:text-sm text-amber-100 leading-relaxed">
                                Real-time match progression, big boundary highlights, milestone alerts & live community blogging for {match.name || 'this match'}.
                            </p>
                        </div>
                        {/* Background watermark */}
                        <div className="absolute -bottom-6 -right-6 text-white/10 font-black text-9xl select-none pointer-events-none">
                            🎙️
                        </div>
                    </div>

                    {/* Live Blogger & Fan Commentary Input Box */}
                    <div className="bg-[#131b2e] border border-white/10 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4">
                        <div className="flex items-center justify-between pb-3 border-b border-white/10">
                            <div className="flex items-center gap-2">
                                <Mic className="w-4 h-4 text-amber-400" />
                                <h3 className="font-black text-sm text-white">Post Live Commentary / Match Note</h3>
                            </div>
                            <span className="text-[11px] text-slate-400">Live for all viewers</span>
                        </div>

                        <form onSubmit={handlePostCommentary} className="space-y-3">
                            {/* Tag Selection Chips */}
                            <div className="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs font-bold">
                                {['4 Runs 🏏', '6 Six 💥', 'Wicket ☝️', 'Milestone 🌟', 'Tactical Note 📋'].map((tag) => (
                                    <button
                                        key={tag}
                                        type="button"
                                        onClick={() => setNewCommentaryTag(tag)}
                                        className={`px-3 py-1 rounded-lg shrink-0 transition text-xs ${
                                            newCommentaryTag === tag
                                                ? 'bg-amber-500 text-slate-950 font-black shadow-md shadow-amber-500/30'
                                                : 'bg-white/5 hover:bg-white/10 text-slate-300 border border-white/5'
                                        }`}
                                    >
                                        {tag}
                                    </button>
                                ))}
                            </div>

                            <div className="relative">
                                <textarea
                                    value={newCommentaryText}
                                    onChange={(e) => setNewCommentaryText(e.target.value)}
                                    placeholder="Add a live commentary moment (e.g. Sensational stroke over cover! India U19 shifting gears now...)"
                                    rows={2}
                                    className="w-full bg-slate-950/80 border border-white/10 rounded-2xl px-4 py-3 text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition resize-none"
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <input
                                        type="text"
                                        value={newPostName}
                                        onChange={(e) => setNewPostName(e.target.value)}
                                        placeholder="Your Name (Optional)"
                                        className="bg-slate-950/60 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-white placeholder-slate-500 w-40 sm:w-48"
                                    />
                                    {commentarySuccessMsg && (
                                        <span className="text-xs text-emerald-400 font-bold flex items-center gap-1">
                                            <CheckCircle2 className="w-3.5 h-3.5" /> {commentarySuccessMsg}
                                        </span>
                                    )}
                                </div>
                                <button
                                    type="submit"
                                    disabled={isPostingCommentary || !newCommentaryText.trim()}
                                    className="px-5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 disabled:opacity-50 text-slate-950 font-black text-xs transition shadow-lg shadow-amber-500/30 flex items-center gap-1.5"
                                >
                                    {isPostingCommentary ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Send className="w-3.5 h-3.5" />}
                                    <span>Post Commentary ⚡</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Live Match Situation & Crease Card */}
                    {miniscore && (
                        <div className="bg-gradient-to-br from-[#18233c] to-[#0f172a] border border-amber-500/20 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-white/10">
                                <div className="flex items-center gap-2.5">
                                    <span className="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
                                    <span className="text-[11px] font-black tracking-wider uppercase text-rose-400">Live Crease</span>
                                    <span className="text-white font-black text-sm sm:text-base">
                                        {liveCreaseBatTeam}{liveCreaseScore ? `: ${liveCreaseScore}` : ''} ({miniscore.overs} Ov)
                                    </span>
                                </div>
                                {miniscore.status && (
                                    <span className="px-3 py-1 rounded-full text-xs font-black bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        {miniscore.status}
                                    </span>
                                )}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="p-3.5 rounded-2xl bg-white/5 border border-white/5 space-y-2">
                                    <div className="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center justify-between">
                                        <span>Batters</span>
                                        <span>R (B) • 4s / 6s</span>
                                    </div>
                                    {miniscore.batsmanStriker?.name && (
                                        <div className="flex items-center justify-between text-xs sm:text-sm font-black text-white">
                                            <span className="flex items-center gap-1.5 text-amber-300">
                                                <span>* {miniscore.batsmanStriker.name}</span>
                                                <span className="text-[9px] px-1.5 py-0.5 rounded bg-amber-400/20 text-amber-300 uppercase">On Strike</span>
                                            </span>
                                            <span>{miniscore.batsmanStriker.runs} ({miniscore.batsmanStriker.balls}) • {miniscore.batsmanStriker.fours}x4 {miniscore.batsmanStriker.sixes}x6</span>
                                        </div>
                                    )}
                                    {miniscore.batsmanNonStriker?.name && (
                                        <div className="flex items-center justify-between text-xs sm:text-sm font-semibold text-slate-300">
                                            <span>{miniscore.batsmanNonStriker.name}</span>
                                            <span>{miniscore.batsmanNonStriker.runs} ({miniscore.batsmanNonStriker.balls}) • {miniscore.batsmanNonStriker.fours}x4 {miniscore.batsmanNonStriker.sixes}x6</span>
                                        </div>
                                    )}
                                </div>

                                <div className="p-3.5 rounded-2xl bg-white/5 border border-white/5 space-y-2">
                                    <div className="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center justify-between">
                                        <span>Bowler</span>
                                        <span>O-M-R-W</span>
                                    </div>
                                    {miniscore.bowlerStriker?.name && (
                                        <div className="flex items-center justify-between text-xs sm:text-sm font-black text-white">
                                            <span className="text-indigo-300">{miniscore.bowlerStriker.name}</span>
                                            <span>{miniscore.bowlerStriker.overs}-{miniscore.bowlerStriker.maidens}-{miniscore.bowlerStriker.runs}-{miniscore.bowlerStriker.wickets}</span>
                                        </div>
                                    )}
                                    {miniscore.recentOvsStats && (
                                        <div className="text-xs text-slate-400 pt-1 border-t border-white/5 flex items-center justify-between">
                                            <span className="font-medium text-slate-500">Recent Balls:</span>
                                            <span className="font-mono text-amber-300 font-bold">{miniscore.recentOvsStats}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Commentary List Container */}
                    <div className="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-7 shadow-2xl space-y-6">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-white/10">
                            <div className="flex items-center gap-2">
                                <span className="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                                <h4 className="font-black text-base text-white">Ball-by-Ball Timeline & Commentary</h4>
                                <span className="px-2 py-0.5 rounded-md bg-white/10 text-[10px] font-mono text-slate-300">
                                    {filteredCommentaryList.length} Entries
                                </span>
                            </div>

                            {/* Filter Pills with Live Counts */}
                            <div className="flex flex-wrap items-center gap-1.5 p-1 bg-black/40 rounded-xl border border-white/10 self-start sm:self-auto text-xs font-bold">
                                <button
                                    type="button"
                                    onClick={() => setCommentaryFilter('all')}
                                    className={`px-3 py-1.5 rounded-lg transition ${
                                        commentaryFilter === 'all' ? 'bg-amber-500 text-slate-950 font-black' : 'text-slate-400 hover:text-white'
                                    }`}
                                >
                                    All ({allCommentaryList.length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setCommentaryFilter('balls')}
                                    className={`px-3 py-1.5 rounded-lg transition ${
                                        commentaryFilter === 'balls' ? 'bg-emerald-600 text-white font-black' : 'text-slate-400 hover:text-white'
                                    }`}
                                >
                                    Deliveries ({allCommentaryList.filter(i => i.type === 'ball').length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setCommentaryFilter('boundaries')}
                                    className={`px-3 py-1.5 rounded-lg transition ${
                                        commentaryFilter === 'boundaries' ? 'bg-blue-600 text-white font-black' : 'text-slate-400 hover:text-white'
                                    }`}
                                >
                                    4s & 6s 💥 ({allCommentaryList.filter(i => i.type === 'ball' && (i.badge === '4' || i.badge === '6' || i.badgeType === 'four' || i.badgeType === 'six')).length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setCommentaryFilter('wickets')}
                                    className={`px-3 py-1.5 rounded-lg transition ${
                                        commentaryFilter === 'wickets' ? 'bg-rose-600 text-white font-black' : 'text-slate-400 hover:text-white'
                                    }`}
                                >
                                    Wickets ☝️ ({allCommentaryList.filter(i => i.type === 'ball' && (i.badge === 'W' || i.badgeType === 'wicket')).length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setCommentaryFilter('notes')}
                                    className={`px-3 py-1.5 rounded-lg transition ${
                                        commentaryFilter === 'notes' ? 'bg-indigo-600 text-white font-black' : 'text-slate-400 hover:text-white'
                                    }`}
                                >
                                    Speeches & Notes 🎙️ ({allCommentaryList.filter(i => i.type !== 'ball').length})
                                </button>
                            </div>
                        </div>

                        {/* Innings Selector & AI Commentary Language Controls */}
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 bg-gradient-to-r from-slate-900 via-black to-slate-900 rounded-2xl border border-white/10 shadow-lg">
                            {/* Innings Tabs */}
                            <div className="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                                <span className="text-[11px] font-black uppercase tracking-wider text-slate-400 shrink-0 flex items-center gap-1.5 mr-1">
                                    <Trophy className="w-3.5 h-3.5 text-indigo-400" /> Innings:
                                </span>
                                {availableCommentaryInnings && availableCommentaryInnings.length > 0 ? (
                                    availableCommentaryInnings.map(inn => {
                                        const isSelected = selectedCommInning === 'live' ? inn.isLive : String(selectedCommInning) === String(inn.id);
                                        return (
                                            <button
                                                key={inn.id}
                                                type="button"
                                                onClick={() => {
                                                    setSelectedCommInning(inn.id);
                                                    setSelectedOverTab('all');
                                                    setExpandedOvers({});
                                                }}
                                                className={`px-3.5 py-1.5 rounded-xl text-xs font-black transition flex items-center gap-2 border shrink-0 ${
                                                    isSelected
                                                        ? 'bg-gradient-to-r from-indigo-600 to-blue-600 text-white border-indigo-400/50 shadow-md shadow-indigo-600/30'
                                                        : 'bg-white/5 hover:bg-white/10 text-slate-300 border-white/10'
                                                }`}
                                            >
                                                {inn.isLive && (
                                                    <span className="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                                                )}
                                                <span>{inn.label}</span>
                                                {inn.isLive && (
                                                    <span className="text-[9px] uppercase px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 font-extrabold">
                                                        Live
                                                    </span>
                                                )}
                                            </button>
                                        );
                                    })
                                ) : (
                                    <span className="text-xs text-slate-400 font-bold px-2">Match Innings</span>
                                )}
                            </div>

                            {/* Commentary Language Switcher */}
                            <div className="flex items-center gap-1.5 p-1 bg-black/60 rounded-xl border border-white/10 shrink-0 self-end sm:self-auto">
                                <button
                                    type="button"
                                    onClick={() => setCommLanguage('en')}
                                    className={`px-3 py-1 rounded-lg text-xs font-black transition flex items-center gap-1.5 ${
                                        commLanguage === 'en'
                                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                                            : 'text-slate-400 hover:text-white'
                                    }`}
                                >
                                    English
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setCommLanguage('hi')}
                                    className={`px-3 py-1 rounded-lg text-xs font-black transition flex items-center gap-1.5 ${
                                        commLanguage === 'hi'
                                            ? 'bg-rose-600 text-white shadow-md shadow-rose-600/30 font-black'
                                            : 'text-slate-400 hover:text-white'
                                    }`}
                                >
                                    <span>🇮🇳</span> हिंदी
                                </button>
                            </div>
                        </div>

                        {/* Over Selector Bar & Group Controls */}
                        {hasNumericOvers && (
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 bg-black/40 rounded-2xl border border-white/10">
                                <div className="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0 scrollbar-none max-w-full">
                                    <span className="text-[11px] font-black uppercase tracking-wider text-slate-400 shrink-0 mr-1 flex items-center gap-1">
                                        <Layers className="w-3.5 h-3.5 text-amber-400" /> Overs:
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedOverTab('all')}
                                        className={`px-3 py-1.5 rounded-xl text-xs font-black transition shrink-0 ${
                                            selectedOverTab === 'all'
                                                ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                                                : 'bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10'
                                        }`}
                                    >
                                        All Overs ({numericOverGroups.length})
                                    </button>

                                    {/* Dropdown Jump for Overs */}
                                    <select
                                        value={selectedOverTab}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            setSelectedOverTab(val);
                                            if (val !== 'all') {
                                                setExpandedOvers(prev => ({ ...prev, [val]: true }));
                                            }
                                        }}
                                        aria-label="Jump to specific over"
                                        className="bg-slate-900 border border-white/15 text-white text-xs font-bold rounded-xl px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    >
                                        <option value="all">Jump to Over...</option>
                                        {numericOverGroups.map(grp => (
                                            <option key={grp.key} value={grp.key}>
                                                Over {grp.overNumber} {grp.isCurrent ? '• (Live)' : ''} ({grp.runs} runs{grp.wickets ? `, ${grp.wickets}W` : ''})
                                            </option>
                                        ))}
                                    </select>

                                    {/* Quick chips for latest 3 overs */}
                                    {numericOverGroups.slice(0, 3).map(grp => {
                                        const isSelected = selectedOverTab === grp.key;
                                        return (
                                            <button
                                                key={grp.key}
                                                type="button"
                                                onClick={() => {
                                                    setSelectedOverTab(grp.key);
                                                    setExpandedOvers(prev => ({ ...prev, [grp.key]: true }));
                                                }}
                                                className={`px-2.5 py-1.5 rounded-xl text-xs font-black transition shrink-0 flex items-center gap-1.5 ${
                                                    isSelected
                                                        ? 'bg-amber-500 text-slate-950 font-black shadow-md shadow-amber-500/30'
                                                        : grp.isCurrent
                                                            ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40 hover:bg-rose-500/30'
                                                            : 'bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10'
                                                }`}
                                            >
                                                {grp.isCurrent && <span className="w-1.5 h-1.5 rounded-full bg-rose-400 animate-pulse"></span>}
                                                <span>{grp.isCurrent ? `Live Over ${grp.overNumber}` : `Over ${grp.overNumber}`}</span>
                                                {grp.wickets > 0 && (
                                                    <span className={`text-[10px] px-1 rounded ${isSelected ? 'bg-black/30 text-slate-950' : 'text-rose-400 font-bold'}`}>
                                                        {grp.wickets}W
                                                    </span>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>

                                <div className="flex items-center gap-2 text-xs shrink-0 self-end sm:self-auto">
                                    <button
                                        type="button"
                                        onClick={expandAllOvers}
                                        className="text-[11px] text-slate-400 hover:text-white underline font-semibold transition"
                                    >
                                        Expand All
                                    </button>
                                    <span className="text-slate-600">•</span>
                                    <button
                                        type="button"
                                        onClick={collapseAllOvers}
                                        className="text-[11px] text-slate-400 hover:text-white underline font-semibold transition"
                                    >
                                        Collapse All
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* List: Either Over-Grouped Accordions or Flat List */}
                        <div className="space-y-4">
                            {isCommentaryLoading && filteredCommentaryList.length === 0 ? (
                                <div className="py-12 text-center text-slate-400 space-y-4">
                                    <Loader2 className="w-8 h-8 mx-auto text-amber-400 animate-spin" />
                                    <p className="text-sm font-bold text-white">Loading live ball-by-ball commentary...</p>
                                    <p className="text-xs text-slate-400">Fetching real-time updates from match engine...</p>
                                </div>
                            ) : filteredCommentaryList.length === 0 ? (
                                <div className="py-12 text-center text-slate-500 space-y-2">
                                    <Radio className="w-8 h-8 mx-auto text-slate-600 animate-pulse" />
                                    <p className="text-sm font-bold text-slate-400">No commentary entries found for this filter.</p>
                                    <p className="text-xs text-slate-500">Live events, deliveries, and post-match notes will appear here.</p>
                                </div>
                            ) : hasNumericOvers ? (
                                (selectedOverTab === 'all' ? overGroups : overGroups.filter(g => g.key === selectedOverTab)).map((grp) => {
                                    const isOpen = isOverExpanded(grp);

                                    // Standalone general notes group (e.g. pre-match preview, toss or post-match summary)
                                    if (!grp.isNumeric) {
                                        return (
                                            <div key={grp.key} className="space-y-3">
                                                <div className="flex items-center gap-2 pt-2 pb-1 border-b border-white/5">
                                                    <span className="text-xs font-black uppercase tracking-wider text-slate-400">
                                                        {grp.title}
                                                    </span>
                                                    <span className="text-[10px] text-slate-500">({grp.items.length})</span>
                                                </div>
                                                <div className="space-y-3">
                                                    {grp.items.map((item, itemIdx) =>
                                                        renderCommentaryCard(item, `${grp.key}-${itemIdx}`, false)
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    }

                                    // Over Accordion Card
                                    return (
                                        <div
                                            key={grp.key}
                                            className={`rounded-2xl border transition-all duration-200 overflow-hidden ${
                                                grp.isCurrent
                                                    ? 'bg-gradient-to-b from-[#18233d] to-[#12192c] border-amber-500/40 shadow-xl shadow-amber-500/5'
                                                    : 'bg-[#151f33]/90 border-white/10 hover:border-white/20'
                                            }`}
                                        >
                                            {/* Over Accordion Header */}
                                            <button
                                                type="button"
                                                onClick={() => toggleOver(grp.key)}
                                                className={`w-full p-4 sm:p-4.5 flex items-center justify-between text-left transition ${
                                                    grp.isCurrent ? 'bg-amber-500/10 hover:bg-amber-500/15' : 'hover:bg-white/5'
                                                }`}
                                            >
                                                <div className="flex items-center gap-3 min-w-0">
                                                    <div className={`px-3 py-1.5 rounded-xl font-mono font-black text-xs sm:text-sm flex items-center gap-1.5 shrink-0 ${
                                                        grp.isCurrent
                                                            ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/30'
                                                            : 'bg-white/10 text-white border border-white/10'
                                                    }`}>
                                                        {grp.isCurrent && <span className="w-2 h-2 rounded-full bg-rose-600 animate-ping"></span>}
                                                        <span>Over {grp.overNumber}</span>
                                                    </div>

                                                    {grp.isCurrent && (
                                                        <span className="hidden xs:inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px] font-black uppercase tracking-wider animate-pulse">
                                                            <span className="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                                            Current Over
                                                        </span>
                                                    )}

                                                    {/* Over Summary Chips */}
                                                    <div className="flex items-center gap-2 text-xs text-slate-400 truncate">
                                                        {grp.runs !== undefined && (
                                                            <span className="font-bold text-slate-200">
                                                                {grp.runs} {grp.runs === 1 ? 'run' : 'runs'}
                                                            </span>
                                                        )}
                                                        {grp.wickets > 0 && (
                                                            <span className="px-2 py-0.5 rounded-md bg-rose-500/20 text-rose-300 text-[11px] font-black border border-rose-500/30">
                                                                {grp.wickets} {grp.wickets === 1 ? 'wicket' : 'wickets'} ☝️
                                                            </span>
                                                        )}
                                                        {grp.bowler && (
                                                            <span className="text-slate-400 hidden sm:inline truncate">
                                                                • Bowler: {grp.bowler}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="flex items-center gap-2 sm:gap-3 shrink-0 ml-2">
                                                    <span className="text-[11px] font-mono text-slate-400 hidden sm:inline">
                                                        {grp.items.length} {grp.items.length === 1 ? 'ball/note' : 'balls/notes'}
                                                    </span>
                                                    <div className={`w-7 h-7 rounded-lg flex items-center justify-center bg-white/5 border border-white/10 text-slate-300 transition-transform duration-200 ${
                                                        isOpen ? 'rotate-180 bg-amber-500/20 text-amber-300 border-amber-500/30' : ''
                                                    }`}>
                                                        <ChevronDown className="w-4 h-4" />
                                                    </div>
                                                </div>
                                            </button>

                                            {/* Over Deliveries & Commentary List (Visible when Open) */}
                                            {isOpen && (
                                                <div className="p-3.5 sm:p-5 pt-2 sm:pt-3 border-t border-white/10 space-y-3 bg-black/20">
                                                    {grp.items.map((item, itemIdx) =>
                                                        renderCommentaryCard(item, `${grp.key}-${itemIdx}`, itemIdx === 3)
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })
                            ) : (
                                filteredCommentaryList.map((item, idx) =>
                                    renderCommentaryCard(item, idx, idx === 3 || (idx > 3 && idx % 10 === 0))
                                )
                            )}
                        </div>
                    </div>
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

            {/* Mobile Sticky Bottom Sponsor Banner */}
            <AdSlot type="sticky-bottom" slotId="match-detail-sticky" />
        </div>
    );
}
