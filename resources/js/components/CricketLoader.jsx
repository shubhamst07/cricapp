import React, { useId } from 'react';

export default function CricketLoader({
    label = 'Loading live match center...',
    sublabel = null,
    compact = false,
    dark = false,
}) {
    const uid = useId().replace(/:/g, '');
    const ballGradId = `cricBallGrad-${uid}`;
    const willowGradId = `cricWillowGrad-${uid}`;

    return (
        <div className={`flex flex-col items-center justify-center text-center ${compact ? 'py-6 gap-2' : 'py-12 gap-3.5'}`}>
            <svg
                className={`${compact ? 'w-20 h-16' : 'w-28 h-24'} overflow-visible`}
                viewBox="0 0 120 90"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <rect
                    x="16"
                    y="79"
                    width="88"
                    height="3"
                    rx="1.5"
                    className={dark ? 'fill-slate-700' : 'fill-slate-300 dark:fill-slate-700'}
                />
                <ellipse
                    cx="45"
                    cy="78"
                    rx="16"
                    ry="3.5"
                    className="fill-indigo-500/35 dark:fill-emerald-400/35 cric-pitch-shadow"
                />

                <g opacity="0.28" className="stroke-slate-500 dark:stroke-slate-400" strokeWidth="2" strokeLinecap="round">
                    <line x1="21" y1="36" x2="21" y2="78" />
                    <line x1="26" y1="36" x2="26" y2="78" />
                    <line x1="31" y1="36" x2="31" y2="78" />
                    <line x1="20" y1="35" x2="32" y2="35" />
                </g>

                <g className="cric-ball-body">
                    <defs>
                        <radialGradient id={ballGradId} cx="35%" cy="30%" r="70%">
                            <stop offset="0%" stopColor="#fb7185" />
                            <stop offset="55%" stopColor="#e11d48" />
                            <stop offset="100%" stopColor="#881337" />
                        </radialGradient>
                    </defs>
                    <circle cx="45" cy="55" r="11.5" fill={`url(#${ballGradId})`} />
                    <g className="cric-ball-seam">
                        <path d="M35.5 51 Q45 55 54.5 59" stroke="#fff1f2" strokeWidth="1.7" strokeDasharray="2.5 2" strokeLinecap="round" />
                        <path d="M36.5 48.5 Q45 52.5 53.5 56.5" stroke="#fecdd3" strokeWidth="1" strokeDasharray="2 2.5" strokeLinecap="round" opacity="0.7" />
                    </g>
                </g>

                <g className="cric-bat-arm">
                    <defs>
                        <linearGradient id={willowGradId} x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stopColor="#fef08a" />
                            <stop offset="55%" stopColor="#f59e0b" />
                            <stop offset="100%" stopColor="#b45309" />
                        </linearGradient>
                    </defs>
                    <rect x="75.5" y="10" width="5" height="22" rx="2.5" className="fill-indigo-600 dark:fill-indigo-400" />
                    <line x1="75.5" y1="15" x2="80.5" y2="15" stroke="#ffffff" strokeWidth="1.2" opacity="0.6" />
                    <line x1="75.5" y1="20" x2="80.5" y2="20" stroke="#ffffff" strokeWidth="1.2" opacity="0.6" />
                    <line x1="75.5" y1="25" x2="80.5" y2="25" stroke="#ffffff" strokeWidth="1.2" opacity="0.6" />
                    <path d="M71 32 L85 32 L87 69 Q87 74 78 75 Q69 74 69 69 Z" fill={`url(#${willowGradId})`} stroke="#b45309" strokeWidth="1" />
                    <line x1="78" y1="33" x2="78" y2="69" stroke="#fef9c3" strokeWidth="1.3" opacity="0.7" strokeLinecap="round" />
                </g>
            </svg>

            <div className="space-y-1">
                <p className={`text-sm font-bold ${dark ? 'text-white' : 'text-slate-800 dark:text-white'}`}>
                    {label}
                </p>
                {sublabel && (
                    <p className={`text-xs ${dark ? 'text-slate-400' : 'text-slate-500 dark:text-slate-400'}`}>
                        {sublabel}
                    </p>
                )}
            </div>
        </div>
    );
}
