import React, { useEffect, useState } from 'react';
import { Sparkles, X, Megaphone, ArrowUpRight } from 'lucide-react';

export default function AdSlot({
    type = 'leaderboard', // 'leaderboard', 'infeed', 'sticky-bottom', 'sidebar'
    slotId = '',
    className = ''
}) {
    const [isDismissed, setIsDismissed] = useState(false);
    const [adsenseClientId, setAdsenseClientId] = useState('');

    useEffect(() => {
        // Read client ID from window if configured in Blade/Env
        if (typeof window !== 'undefined') {
            const id = window.ADSENSE_CLIENT_ID || document.querySelector('meta[name="adsense-client"]')?.getAttribute('content') || '';
            setAdsenseClientId(id);
            if (id && window.adsbygoogle) {
                try {
                    (window.adsbygoogle = window.adsbygoogle || []).push({});
                } catch (e) {
                    // ignore
                }
            }
        }
    }, [type, slotId]);

    if (isDismissed) return null;

    // Real Google AdSense unit if publisher ID is configured
    if (adsenseClientId && slotId) {
        return (
            <div className={`overflow-hidden text-center my-3 ${className}`}>
                <span className="block text-[9px] uppercase tracking-wider text-slate-500 font-bold mb-1">
                    Advertisement
                </span>
                <ins
                    className="adsbygoogle block"
                    style={{ display: 'block' }}
                    data-ad-client={adsenseClientId}
                    data-ad-slot={slotId}
                    data-ad-format="auto"
                    data-full-width-responsive="true"
                ></ins>
            </div>
        );
    }

    // Default Pro Sports Sponsorship & Ad Placeholder (App-ready for approval)
    if (type === 'sticky-bottom') {
        return (
            <div className="fixed bottom-0 left-0 right-0 z-40 bg-gradient-to-r from-slate-950 via-[#0e1628] to-slate-950 border-t border-amber-500/30 shadow-2xl px-4 py-2.5 flex items-center justify-between sm:justify-center gap-3 backdrop-blur-md">
                <div className="flex items-center gap-2.5">
                    <span className="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 text-[9px] font-black uppercase tracking-wider border border-amber-500/30 shrink-0">
                        Ad Space
                    </span>
                    <p className="text-xs text-slate-200 font-medium truncate max-w-[240px] sm:max-w-md">
                        <strong className="text-white">LiveCricket Partner Network:</strong> Promote your turf, brand, or fantasy app here.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <a
                        href="/contact"
                        className="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-[11px] font-black tracking-wide transition inline-flex items-center gap-1 shrink-0"
                    >
                        <span>Sponsor</span>
                        <ArrowUpRight className="w-3 h-3" />
                    </a>
                    <button
                        type="button"
                        onClick={() => setIsDismissed(true)}
                        className="p-1 rounded-md text-slate-400 hover:text-white hover:bg-white/10 transition"
                        title="Close Ad"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        );
    }

    if (type === 'infeed') {
        return (
            <div className={`p-4 rounded-2xl bg-gradient-to-r from-[#111c33] via-[#0d1627] to-[#111c33] border border-amber-500/20 shadow-md relative overflow-hidden my-3 ${className}`}>
                <div className="flex items-center justify-between pb-2 border-b border-white/5 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                    <span className="flex items-center gap-1 text-amber-400">
                        <Sparkles className="w-3 h-3 text-amber-400 animate-pulse" />
                        Featured Match Sponsor
                    </span>
                    <span className="text-[9px] text-slate-500">Google Ads Partner</span>
                </div>
                <div className="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div className="space-y-1">
                        <h4 className="text-xs sm:text-sm font-black text-white flex items-center gap-1.5">
                            🏏 Cricket Gear & Local Turf Arena
                        </h4>
                        <p className="text-xs text-slate-300 leading-relaxed font-sans">
                            Book floodlight turf grounds, buy premium English willow bats, and hire verified umpires across India.
                        </p>
                    </div>
                    <a
                        href="/grounds"
                        className="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black text-xs transition shadow-md shadow-amber-500/20 self-start sm:self-auto shrink-0 flex items-center gap-1.5"
                    >
                        <span>Explore Grounds</span>
                        <ArrowUpRight className="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        );
    }

    // Default Leaderboard Banner (728x90 style)
    return (
        <div className={`w-full max-w-5xl mx-auto rounded-2xl bg-gradient-to-r from-slate-900 via-[#101b30] to-slate-900 border border-white/10 p-3 sm:p-4 text-center relative overflow-hidden shadow-lg my-4 ${className}`}>
            <div className="flex items-center justify-between mb-1.5 text-[9px] text-slate-500 font-bold uppercase tracking-wider">
                <span className="flex items-center gap-1 text-amber-400 font-black">
                    <Megaphone className="w-3 h-3 text-amber-400" />
                    Sponsored
                </span>
                <span>Google AdSense Slot • 728x90</span>
            </div>
            <div className="flex flex-col sm:flex-row items-center justify-between gap-2.5 py-1">
                <div className="text-left space-y-0.5">
                    <div className="text-xs sm:text-sm font-black text-white flex items-center gap-1.5">
                        <span className="text-amber-400">⚡</span>
                        <span>Promote Your Brand During Live Cricket Matches</span>
                    </div>
                    <p className="text-[11px] sm:text-xs text-slate-400">
                        Reach high-intent cricket fans during live overs, tournament highlights, and player scorecards.
                    </p>
                </div>
                <a
                    href="/contact"
                    className="px-3.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-black text-xs transition border border-white/15 shrink-0 inline-flex items-center gap-1.5"
                >
                    <span>Advertise Here</span>
                    <ArrowUpRight className="w-3 h-3 text-amber-400" />
                </a>
            </div>
        </div>
    );
}
