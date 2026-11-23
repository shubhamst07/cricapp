import React, { useEffect, useState } from 'react';

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

    // ONLY display ad container if real Google AdSense publisher ID is configured
    if (!adsenseClientId || !slotId) {
        return null;
    }

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
