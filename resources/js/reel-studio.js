/**
 * CricApp 9:16 Reel Studio Renderer & Audio Mixer
 * Runs purely in-browser on 720x1280 HTML5 Canvas + Web Audio API,
 * using same-origin image proxy (/admin/reels/proxy-image) and optional
 * server Edge TTS (/admin/reels/tts) + FFmpeg MP4 (/admin/reels/convert-mp4).
 */

const CANVAS_W = 720;
const CANVAS_H = 1280;

const THEMES = {
    stadium: {
        name: 'Stadium Night',
        bg: '#050b18',
        topTint: 'rgba(6, 13, 30, 0.72)',
        bottomTint: 'rgba(4, 9, 20, 0.94)',
        accent: '#10b981',
        accentText: '#042f1f',
        highlight: '#f59e0b',
        badgeBg: 'rgba(16, 185, 129, 0.22)',
        badgeBorder: 'rgba(52, 211, 153, 0.65)',
        badgeColor: '#6ee7b7',
        brandBg: '#10b981',
    },
    crimson: {
        name: 'Matchday Red',
        bg: '#12050b',
        topTint: 'rgba(20, 6, 14, 0.74)',
        bottomTint: 'rgba(15, 4, 10, 0.95)',
        accent: '#f43f5e',
        accentText: '#fff1f2',
        highlight: '#fbbf24',
        badgeBg: 'rgba(244, 63, 94, 0.24)',
        badgeBorder: 'rgba(251, 113, 133, 0.7)',
        badgeColor: '#fda4af',
        brandBg: '#e11d48',
    },
    pitch: {
        name: 'Gold & Turf',
        bg: '#04120b',
        topTint: 'rgba(4, 18, 11, 0.72)',
        bottomTint: 'rgba(3, 14, 9, 0.95)',
        accent: '#f59e0b',
        accentText: '#1c1002',
        highlight: '#34d399',
        badgeBg: 'rgba(245, 158, 11, 0.24)',
        badgeBorder: 'rgba(251, 191, 36, 0.7)',
        badgeColor: '#fcd34d',
        brandBg: '#d97706',
    },
};

function clamp(val, min, max) {
    return Math.min(max, Math.max(min, val));
}

function easeOutCubic(t) {
    const x = clamp(t, 0, 1);
    return 1 - Math.pow(1 - x, 3);
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function base64ToArrayBuffer(b64) {
    const binary = atob(b64);
    const len = binary.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

function drawRoundedRect(ctx, x, y, w, h, r) {
    const radius = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.arcTo(x + w, y, x + w, y + h, radius);
    ctx.arcTo(x + w, y + h, x, y + h, radius);
    ctx.arcTo(x, y + h, x, y, radius);
    ctx.arcTo(x, y, x + w, y, radius);
    ctx.closePath();
}

function wrapLines(ctx, text, maxWidth, maxLines) {
    const clean = String(text || '').replace(/\s+/g, ' ').trim();
    if (!clean) return [];

    const words = clean.split(' ');
    const lines = [];
    let current = '';

    for (let i = 0; i < words.length; i++) {
        const word = words[i];
        const testLine = current ? `${current} ${word}` : word;
        if (ctx.measureText(testLine).width <= maxWidth) {
            current = testLine;
        } else {
            if (current) lines.push(current);
            current = word;
            if (lines.length >= maxLines) {
                break;
            }
        }
    }
    if (current && lines.length < maxLines) {
        lines.push(current);
    } else if (lines.length === maxLines && current) {
        const lastIdx = maxLines - 1;
        while (ctx.measureText(lines[lastIdx] + '...').width > maxWidth && lines[lastIdx].length > 4) {
            lines[lastIdx] = lines[lastIdx].slice(0, -1).trim();
        }
        if (!lines[lastIdx].endsWith('...')) {
            lines[lastIdx] += '...';
        }
    }

    return lines;
}

/**
 * Procedural royalty-free cricket percussion groove generated via Web Audio API.
 */
async function buildProceduralBeatBuffer(audioCtx, style, durationSec = 4.0) {
    if (style === 'off') return null;

    const sampleRate = audioCtx.sampleRate || 44100;
    const totalSamples = Math.floor(sampleRate * durationSec);
    const offline = new OfflineAudioContext(1, totalSamples, sampleRate);

    const stepSec = style === 'hype' ? 0.25 : 0.5;
    const steps = Math.floor(durationSec / stepSec);

    for (let i = 0; i < steps; i++) {
        const t = i * stepSec;

        // Kick on downbeats
        if (i % 2 === 0) {
            const osc = offline.createOscillator();
            const gain = offline.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(128, t);
            osc.frequency.exponentialRampToValueAtTime(42, t + 0.14);
            gain.gain.setValueAtTime(0.55, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.18);
            osc.connect(gain);
            gain.connect(offline.destination);
            osc.start(t);
            osc.stop(t + 0.19);
        }

        // Crisp hi-hat / shaker pulse
        const noiseLen = Math.floor(sampleRate * 0.045);
        const noiseBuf = offline.createBuffer(1, noiseLen, sampleRate);
        const data = noiseBuf.getChannelData(0);
        for (let j = 0; j < noiseLen; j++) {
            data[j] = (Math.random() * 2 - 1) * Math.exp(-j / (noiseLen * 0.3));
        }
        const noise = offline.createBufferSource();
        noise.buffer = noiseBuf;
        const hp = offline.createBiquadFilter();
        hp.type = 'highpass';
        hp.frequency.value = 5200;
        const ng = offline.createGain();
        ng.gain.setValueAtTime(i % 2 === 1 ? 0.22 : 0.1, t);
        ng.gain.exponentialRampToValueAtTime(0.001, t + 0.05);
        noise.connect(hp);
        hp.connect(ng);
        ng.connect(offline.destination);
        noise.start(t);

        // Subtle warm bass pad note on bar start
        if (i % 4 === 0) {
            const bass = offline.createOscillator();
            const bg = offline.createGain();
            bass.type = 'triangle';
            const notes = [65.41, 73.42, 82.41, 61.74];
            bass.frequency.setValueAtTime(notes[(i / 4) % notes.length], t);
            bg.gain.setValueAtTime(0.18, t);
            bg.gain.exponentialRampToValueAtTime(0.001, t + stepSec * 3.6);
            bass.connect(bg);
            bg.connect(offline.destination);
            bass.start(t);
            bass.stop(t + stepSec * 3.8);
        }
    }

    return offline.startRendering();
}

export function initReelStudio(root) {
    let initialProject;
    let curatedImages = [];
    try {
        initialProject = JSON.parse(root.getAttribute('data-project') || '{}');
        curatedImages = JSON.parse(root.getAttribute('data-curated-images') || '[]');
    } catch (e) {
        console.error('Failed to parse Reel Studio project JSON', e);
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const ttsEndpoint = root.getAttribute('data-tts-url') || '/admin/reels/tts';
    const convertEndpoint = root.getAttribute('data-convert-url') || '/admin/reels/convert-mp4';
    const proxyEndpoint = root.getAttribute('data-proxy-url') || '/admin/reels/proxy-image';
    const studioBaseUrl = root.getAttribute('data-studio-url') || '/admin/reels';

    // DOM Elements
    const canvas = document.getElementById('reel-canvas');
    const ctx = canvas.getContext('2d');
    const slidesListEl = document.getElementById('reel-slides-list');
    const addSlideBtn = document.getElementById('btn-add-slide');
    const themeSelect = document.getElementById('reel-theme');
    const voiceSelect = document.getElementById('reel-voice');
    const voiceRateSelect = document.getElementById('reel-voice-rate');
    const beatSelect = document.getElementById('reel-beat-style');
    const musicVolInput = document.getElementById('reel-music-volume');
    const captionsToggle = document.getElementById('reel-captions-toggle');
    const convertMp4Toggle = document.getElementById('reel-convert-mp4-toggle');
    const generateTtsBtn = document.getElementById('btn-generate-tts');
    const clearVoiceBtn = document.getElementById('btn-clear-voice');
    const recordMicBtn = document.getElementById('btn-record-mic');
    const uploadVoiceInput = document.getElementById('input-upload-voice');
    const uploadMusicInput = document.getElementById('input-upload-music');
    const previewBtn = document.getElementById('btn-preview-reel');
    const exportBtn = document.getElementById('btn-export-reel');
    const articlePicker = document.getElementById('reel-article-picker');
    const loadArticleBtn = document.getElementById('btn-load-article');
    const statusBox = document.getElementById('reel-status-box');
    const timeLabel = document.getElementById('reel-time-label');
    const progressRange = document.getElementById('reel-scrub-bar');
    const captionTextarea = document.getElementById('reel-social-caption');
    const copyCaptionBtn = document.getElementById('btn-copy-caption');
    const exportResultBox = document.getElementById('reel-export-result');
    const exportVideoPreview = document.getElementById('reel-export-video');
    const exportDownloadLink = document.getElementById('reel-export-download');
    const voiceStatusBadge = document.getElementById('reel-voice-status');

    // Mutable State
    const state = {
        title: initialProject.title || 'CricApp Reel',
        theme: initialProject.theme || 'stadium',
        voice: initialProject.voice || 'en-IN-PrabhatNeural',
        rate: '+5%',
        beatStyle: 'pulse',
        musicVolume: 0.25,
        showCaptions: true,
        convertMp4: true,
        slides: Array.isArray(initialProject.slides) && initialProject.slides.length
            ? initialProject.slides.map((s) => ({ ...s, duration: Number(s.duration) || 5.0 }))
            : [],
        selectedSlideIndex: 0,
        playing: false,
        recording: false,
        micRecording: false,
        playhead: 0,
        audioCtx: null,
        activeAudioNodes: [],
        // Audio tracks: per-slide TTS OR single uploaded/recorded voice track
        slideVoiceBuffers: [], // Array<{ buffer: AudioBuffer|null, boundaries: Array, text: string }>
        masterVoiceBuffer: null,
        customMusicBuffer: null,
        micRecorder: null,
        micChunks: [],
        imageCache: new Map(),
        objectUrls: [],
    };

    if (themeSelect) themeSelect.value = state.theme;
    if (voiceSelect) voiceSelect.value = state.voice;
    if (captionTextarea && initialProject.social_caption) {
        captionTextarea.value = initialProject.social_caption;
    }

    function setStatus(msg, tone = 'info') {
        if (!statusBox) return;
        const colors = {
            info: 'bg-slate-800/80 border-white/10 text-slate-200',
            success: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300',
            warn: 'bg-amber-500/15 border-amber-500/30 text-amber-300',
            error: 'bg-rose-500/15 border-rose-500/30 text-rose-300',
        };
        statusBox.className = `px-3.5 py-2.5 rounded-xl border text-xs font-semibold transition ${colors[tone] || colors.info}`;
        statusBox.textContent = msg;
        statusBox.classList.remove('hidden');
    }

    function updateVoiceBadge(text, active = false) {
        if (!voiceStatusBadge) return;
        voiceStatusBadge.textContent = text;
        voiceStatusBadge.className = active
            ? 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
            : 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-800 text-slate-400 border border-white/10';
    }

    function ensureAudioContext() {
        if (!state.audioCtx) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            state.audioCtx = new Ctx();
        }
        if (state.audioCtx.state === 'suspended') {
            state.audioCtx.resume();
        }
        return state.audioCtx;
    }

    function resolveImageSrc(rawUrl) {
        const clean = String(rawUrl || '').trim();
        if (!clean) return '';
        if (clean.startsWith('blob:') || clean.startsWith('data:') || clean.startsWith('/')) {
            return clean;
        }
        if (clean.startsWith('http://') || clean.startsWith('https://')) {
            // Same-origin already? Load directly
            if (clean.startsWith(window.location.origin + '/')) {
                return clean;
            }
            return `${proxyEndpoint}?url=${encodeURIComponent(clean)}`;
        }
        return clean;
    }

    function loadCanvasImage(rawUrl) {
        const resolved = resolveImageSrc(rawUrl);
        if (!resolved) return null;

        const cached = state.imageCache.get(resolved);
        if (cached) return cached.loaded ? cached.img : null;

        const entry = { img: new Image(), loaded: false, error: false };
        entry.img.crossOrigin = 'anonymous';
        entry.img.onload = () => {
            entry.loaded = true;
            if (!state.playing && !state.recording) {
                renderAt(state.playhead);
            }
        };
        entry.img.onerror = () => {
            entry.error = true;
        };
        entry.img.src = resolved;
        state.imageCache.set(resolved, entry);
        return null;
    }

    function getTimeline() {
        let cursor = 0;
        const segments = state.slides.map((slide, index) => {
            const dur = clamp(Number(slide.duration) || 5.0, 2.0, 25.0);
            const seg = { index, slide, start: cursor, end: cursor + dur, duration: dur };
            cursor += dur;
            return seg;
        });
        return { segments, total: Math.max(cursor, 2.0) };
    }

    function findSegmentAt(timeSec, timeline) {
        for (const seg of timeline.segments) {
            if (timeSec < seg.end) return seg;
        }
        return timeline.segments[timeline.segments.length - 1];
    }

    function getCaptionStateAt(seg, localSec) {
        const slideVoice = state.slideVoiceBuffers[seg.index];
        const narration = String(seg.slide.narration || '').trim();
        if (!narration) return null;

        // Use exact Edge TTS WordBoundary timings when present
        if (slideVoice && Array.isArray(slideVoice.boundaries) && slideVoice.boundaries.length > 0) {
            const boundaries = slideVoice.boundaries;
            const localMs = localSec * 1000;
            let activeIdx = -1;
            for (let i = 0; i < boundaries.length; i++) {
                const b = boundaries[i];
                const nextStart = i + 1 < boundaries.length ? boundaries[i + 1].offset_ms : (b.offset_ms + b.duration_ms + 300);
                if (localMs >= b.offset_ms && localMs < nextStart) {
                    activeIdx = i;
                    break;
                }
            }
            if (activeIdx === -1 && localMs >= boundaries[boundaries.length - 1].offset_ms) {
                activeIdx = boundaries.length - 1;
            }
            const chunkSize = 5;
            const idx = Math.max(0, activeIdx);
            const windowStart = Math.floor(idx / chunkSize) * chunkSize;
            const slice = boundaries.slice(windowStart, windowStart + chunkSize);
            return {
                words: slice.map((b) => b.text),
                activeOffset: idx - windowStart,
            };
        }

        // Fallback proportional caption pacing across words
        const words = narration.split(/\s+/).filter(Boolean);
        if (!words.length) return null;
        const speakWindow = Math.max(seg.duration - 0.4, 1.0);
        const progress = clamp(localSec / speakWindow, 0, 0.999);
        const activeIdx = Math.floor(progress * words.length);
        const chunkSize = 5;
        const windowStart = Math.floor(activeIdx / chunkSize) * chunkSize;
        return {
            words: words.slice(windowStart, windowStart + chunkSize),
            activeOffset: activeIdx - windowStart,
        };
    }

    function renderAt(timeSec) {
        const timeline = getTimeline();
        const t = clamp(timeSec, 0, timeline.total);
        const seg = findSegmentAt(t, timeline);
        if (!seg) return;

        const theme = THEMES[state.theme] || THEMES.stadium;
        const localSec = t - seg.start;
        const slideProgress = clamp(localSec / seg.duration, 0, 1);
        const enterEase = easeOutCubic(clamp(localSec / 0.38, 0, 1));
        const isLastSlide = seg.index === timeline.segments.length - 1;

        // 1. Background Fill
        ctx.fillStyle = theme.bg;
        ctx.fillRect(0, 0, CANVAS_W, CANVAS_H);

        // 2. Background Image with Ken Burns smooth zoom & pan
        const img = loadCanvasImage(seg.slide.image);
        if (img && img.naturalWidth > 0) {
            const zoom = 1.05 + slideProgress * 0.11;
            const panDir = seg.index % 2 === 0 ? 1 : -1;
            const panX = panDir * (slideProgress - 0.5) * 32;

            const scale = Math.max(CANVAS_W / img.naturalWidth, CANVAS_H / img.naturalHeight) * zoom;
            const drawW = img.naturalWidth * scale;
            const drawH = img.naturalHeight * scale;
            const dx = (CANVAS_W - drawW) / 2 + panX;
            const dy = (CANVAS_H - drawH) / 2;

            ctx.save();
            ctx.globalAlpha = 0.92;
            ctx.drawImage(img, dx, dy, drawW, drawH);
            ctx.restore();
        } else {
            // Stylish radial stadium glow fallback
            const rad = ctx.createRadialGradient(CANVAS_W / 2, CANVAS_H * 0.35, 40, CANVAS_W / 2, CANVAS_H * 0.45, 760);
            rad.addColorStop(0, theme.badgeBg);
            rad.addColorStop(1, theme.bg);
            ctx.fillStyle = rad;
            ctx.fillRect(0, 0, CANVAS_W, CANVAS_H);
        }

        // 3. Cinematic Top + Bottom Vignette Gradients
        const topGrad = ctx.createLinearGradient(0, 0, 0, 420);
        topGrad.addColorStop(0, theme.topTint);
        topGrad.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = topGrad;
        ctx.fillRect(0, 0, CANVAS_W, 420);

        const bottomGrad = ctx.createLinearGradient(0, 420, 0, CANVAS_H);
        bottomGrad.addColorStop(0, 'rgba(0,0,0,0.08)');
        bottomGrad.addColorStop(0.38, 'rgba(4, 9, 20, 0.72)');
        bottomGrad.addColorStop(0.72, theme.bottomTint);
        bottomGrad.addColorStop(1, '#020612');
        ctx.fillStyle = bottomGrad;
        ctx.fillRect(0, 420, CANVAS_W, CANVAS_H - 420);

        // Subtle stadium grid accent lines
        ctx.save();
        ctx.strokeStyle = 'rgba(255,255,255,0.04)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(44, 108);
        ctx.lineTo(CANVAS_W - 44, 108);
        ctx.stroke();
        ctx.restore();

        // 4. Top Story Segmented Progress Bars
        const barGap = 8;
        const marginX = 40;
        const barTotalW = CANVAS_W - marginX * 2 - barGap * (timeline.segments.length - 1);
        const barW = barTotalW / timeline.segments.length;
        for (let i = 0; i < timeline.segments.length; i++) {
            const bx = marginX + i * (barW + barGap);
            drawRoundedRect(ctx, bx, 36, barW, 6, 3);
            ctx.fillStyle = 'rgba(255,255,255,0.22)';
            ctx.fill();

            let fillRatio = 0;
            if (i < seg.index) fillRatio = 1;
            else if (i === seg.index) fillRatio = slideProgress;
            if (fillRatio > 0) {
                drawRoundedRect(ctx, bx, 36, Math.max(6, barW * fillRatio), 6, 3);
                ctx.fillStyle = theme.accent;
                ctx.fill();
            }
        }

        // 5. Brand Pill & Slide Counter
        ctx.save();
        drawRoundedRect(ctx, 40, 62, 188, 38, 19);
        ctx.fillStyle = theme.brandBg;
        ctx.fill();
        ctx.fillStyle = '#ffffff';
        ctx.font = '800 18px Inter, system-ui, -apple-system, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('CRICAPP.IN', 134, 82);

        const counterText = `${seg.index + 1} / ${timeline.segments.length}`;
        drawRoundedRect(ctx, CANVAS_W - 122, 62, 82, 38, 19);
        ctx.fillStyle = 'rgba(15, 23, 42, 0.72)';
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.18)';
        ctx.lineWidth = 1.5;
        ctx.stroke();
        ctx.fillStyle = '#e2e8f0';
        ctx.font = '700 17px Inter, system-ui, sans-serif';
        ctx.fillText(counterText, CANVAS_W - 81, 82);
        ctx.restore();

        // 6. Slide Content Card (Animated Entrance)
        const slideOffsetY = (1 - enterEase) * 34;
        ctx.save();
        ctx.globalAlpha = enterEase;
        ctx.translate(0, slideOffsetY);

        const contentX = 44;
        const maxTextW = CANVAS_W - 88;
        let cursorY = isLastSlide ? 560 : 610;

        // Category / Step Badge
        const badgeLabel = String(seg.slide.badge || 'CRICKET UPDATE').toUpperCase();
        ctx.font = '800 19px Inter, system-ui, sans-serif';
        const badgeW = Math.min(maxTextW, ctx.measureText(badgeLabel).width + 44);
        drawRoundedRect(ctx, contentX, cursorY, badgeW, 40, 12);
        ctx.fillStyle = theme.badgeBg;
        ctx.fill();
        ctx.strokeStyle = theme.badgeBorder;
        ctx.lineWidth = 1.5;
        ctx.stroke();
        ctx.fillStyle = theme.badgeColor;
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText(badgeLabel, contentX + 22, cursorY + 21);

        cursorY += 66;

        // Main Headline
        ctx.font = '900 48px Inter, system-ui, -apple-system, sans-serif';
        ctx.fillStyle = '#ffffff';
        ctx.shadowColor = 'rgba(0,0,0,0.65)';
        ctx.shadowBlur = 16;
        ctx.shadowOffsetY = 4;
        const headLines = wrapLines(ctx, seg.slide.headline || '', maxTextW, 3);
        ctx.textBaseline = 'top';
        for (const line of headLines) {
            ctx.fillText(line, contentX, cursorY);
            cursorY += 58;
        }
        ctx.shadowBlur = 0;
        ctx.shadowOffsetY = 0;

        // Accent bar
        cursorY += 8;
        drawRoundedRect(ctx, contentX, cursorY, 76, 6, 3);
        ctx.fillStyle = theme.highlight;
        ctx.fill();
        cursorY += 24;

        // Subtext
        ctx.font = '600 28px Inter, system-ui, sans-serif';
        ctx.fillStyle = '#e2e8f0';
        const subLines = wrapLines(ctx, seg.slide.subtext || '', maxTextW, 3);
        for (const line of subLines) {
            ctx.fillText(line, contentX, cursorY);
            cursorY += 38;
        }

        // Last slide CTA pill
        if (isLastSlide) {
            cursorY += 22;
            drawRoundedRect(ctx, contentX, cursorY, 360, 62, 18);
            ctx.fillStyle = theme.highlight;
            ctx.fill();
            ctx.fillStyle = '#090d16';
            ctx.font = '900 24px Inter, system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('VISIT WWW.CRICAPP.IN', contentX + 180, cursorY + 32);
        }

        ctx.restore();

        // 7. Animated Karaoke Word Captions (above Instagram/Shorts safe zone)
        if (state.showCaptions) {
            const cap = getCaptionStateAt(seg, localSec);
            if (cap && cap.words.length > 0) {
                ctx.save();
                ctx.font = '800 26px Inter, system-ui, sans-serif';
                const wordWidths = cap.words.map((w) => ctx.measureText(w).width);
                const spaceW = 11;
                const totalW = wordWidths.reduce((a, b) => a + b, 0) + spaceW * (cap.words.length - 1);
                const pillW = Math.min(CANVAS_W - 64, totalW + 46);
                const pillX = (CANVAS_W - pillW) / 2;
                const pillY = 1090;

                drawRoundedRect(ctx, pillX, pillY, pillW, 56, 16);
                ctx.fillStyle = 'rgba(2, 6, 18, 0.84)';
                ctx.fill();
                ctx.strokeStyle = 'rgba(255,255,255,0.16)';
                ctx.lineWidth = 1.5;
                ctx.stroke();

                let wx = pillX + (pillW - totalW) / 2;
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                cap.words.forEach((word, idx) => {
                    ctx.fillStyle = idx === cap.activeOffset ? theme.highlight : '#f8fafc';
                    ctx.fillText(word, wx, pillY + 29);
                    wx += wordWidths[idx] + spaceW;
                });
                ctx.restore();
            }
        }

        // 8. Persistent Safe Footer Watermark
        ctx.save();
        ctx.fillStyle = 'rgba(203, 213, 225, 0.72)';
        ctx.font = '700 17px Inter, system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('Live Scores  •  Ball-by-Ball  •  Cricket News @ CricApp.in', CANVAS_W / 2, 1205);
        ctx.restore();

        // Update time UI
        if (timeLabel) {
            timeLabel.textContent = `${t.toFixed(1)}s / ${timeline.total.toFixed(1)}s`;
        }
        if (progressRange && document.activeElement !== progressRange) {
            progressRange.max = String(timeline.total);
            progressRange.value = String(t);
        }
    }

    function stopAudioPlayback() {
        state.activeAudioNodes.forEach((node) => {
            try {
                node.stop();
            } catch (_) {
                // Already stopped
            }
        });
        state.activeAudioNodes = [];
    }

    async function scheduleAudioGraph(destinationNode, offsetSec = 0) {
        const audioCtx = ensureAudioContext();
        const timeline = getTimeline();
        const startAt = audioCtx.currentTime + 0.04;
        const hasAnyVoice = Boolean(
            state.masterVoiceBuffer || state.slideVoiceBuffers.some((v) => v && v.buffer)
        );

        // Background music (custom file or procedural Web Audio beat)
        if (state.beatStyle !== 'off' || state.customMusicBuffer) {
            let musicBuf = state.customMusicBuffer;
            if (!musicBuf) {
                musicBuf = await buildProceduralBeatBuffer(audioCtx, state.beatStyle, 4.0);
            }
            if (musicBuf) {
                const src = audioCtx.createBufferSource();
                src.buffer = musicBuf;
                src.loop = true;

                const gainNode = audioCtx.createGain();
                // Auto-duck background music when voiceover is present
                const baseVol = clamp(state.musicVolume, 0, 1);
                gainNode.gain.value = hasAnyVoice ? baseVol * 0.28 : baseVol * 0.65;

                src.connect(gainNode);
                gainNode.connect(destinationNode);

                const musicOffset = offsetSec % musicBuf.duration;
                const remaining = Math.max(0.1, timeline.total - offsetSec);
                src.start(startAt, musicOffset, remaining);
                state.activeAudioNodes.push(src);
            }
        }

        // Master single voice track (Mic / Uploaded MP3)
        if (state.masterVoiceBuffer) {
            if (offsetSec < state.masterVoiceBuffer.duration) {
                const vSrc = audioCtx.createBufferSource();
                vSrc.buffer = state.masterVoiceBuffer;
                const vGain = audioCtx.createGain();
                vGain.gain.value = 1.0;
                vSrc.connect(vGain);
                vGain.connect(destinationNode);
                vSrc.start(startAt, offsetSec);
                state.activeAudioNodes.push(vSrc);
            }
            return;
        }

        // Per-slide synthesized TTS buffers
        timeline.segments.forEach((seg) => {
            const voiceItem = state.slideVoiceBuffers[seg.index];
            if (!voiceItem || !voiceItem.buffer) return;
            if (seg.end <= offsetSec) return;

            const vSrc = audioCtx.createBufferSource();
            vSrc.buffer = voiceItem.buffer;
            const vGain = audioCtx.createGain();
            vGain.gain.value = 1.05;
            vSrc.connect(vGain);
            vGain.connect(destinationNode);

            if (offsetSec <= seg.start) {
                vSrc.start(startAt + (seg.start - offsetSec));
            } else {
                const trim = offsetSec - seg.start;
                if (trim < voiceItem.buffer.duration) {
                    vSrc.start(startAt, trim);
                }
            }
            state.activeAudioNodes.push(vSrc);
        });
    }

    async function togglePreviewPlayback() {
        if (state.playing) {
            state.playing = false;
            stopAudioPlayback();
            if (previewBtn) previewBtn.textContent = '▶ Play Preview';
            return;
        }

        const timeline = getTimeline();
        if (state.playhead >= timeline.total - 0.1) {
            state.playhead = 0;
        }

        const audioCtx = ensureAudioContext();
        await scheduleAudioGraph(audioCtx.destination, state.playhead);

        state.playing = true;
        if (previewBtn) previewBtn.textContent = '⏸ Pause';

        const startPerf = performance.now();
        const startPlayhead = state.playhead;

        const tick = (now) => {
            if (!state.playing || state.recording) return;
            const elapsed = startPlayhead + (now - startPerf) / 1000;
            state.playhead = elapsed;
            renderAt(elapsed);

            if (elapsed >= timeline.total) {
                state.playing = false;
                state.playhead = 0;
                stopAudioPlayback();
                renderAt(0);
                if (previewBtn) previewBtn.textContent = '▶ Play Preview';
                return;
            }
            requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }

    async function generateAllSlidesTts() {
        if (!state.slides.length) return;
        const audioCtx = ensureAudioContext();
        const voice = voiceSelect?.value || 'en-IN-PrabhatNeural';
        const rate = voiceRateSelect?.value || '+5%';

        generateTtsBtn.disabled = true;
        const origLabel = generateTtsBtn.textContent;
        state.masterVoiceBuffer = null;

        try {
            const newBuffers = [];
            for (let i = 0; i < state.slides.length; i++) {
                const slide = state.slides[i];
                const speechText = String(slide.narration || slide.headline || '').trim();
                generateTtsBtn.textContent = `🎙️ Generating ${i + 1}/${state.slides.length}...`;

                if (!speechText) {
                    newBuffers.push(null);
                    continue;
                }

                const resp = await fetch(ttsEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        text: speechText,
                        voice,
                        rate,
                        pitch: '+0Hz',
                    }),
                });

                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data.audio_base64) {
                    throw new Error(data.message || 'Voice synthesis failed.');
                }

                const arrayBuf = base64ToArrayBuffer(data.audio_base64);
                const decoded = await audioCtx.decodeAudioData(arrayBuf);
                // Match slide duration tightly to natural speech + 0.45s breathing gap
                slide.duration = Number(clamp(decoded.duration + 0.45, 2.5, 20.0).toFixed(1));
                newBuffers.push({
                    buffer: decoded,
                    boundaries: Array.isArray(data.boundaries) ? data.boundaries : [],
                    text: speechText,
                });
            }

            state.slideVoiceBuffers = newBuffers;
            renderSlidesEditor();
            renderAt(state.playhead);
            updateVoiceBadge(`AI Voice Ready (${voice.split('-')[2] || voice})`, true);
            setStatus(`Voiceover synced across ${state.slides.length} slides! Click Play Preview or Export Reel.`, 'success');
        } catch (err) {
            setStatus(err.message || 'Could not generate AI voiceover.', 'warn');
        } finally {
            generateTtsBtn.disabled = false;
            generateTtsBtn.textContent = origLabel;
        }
    }

    async function toggleMicRecording() {
        if (state.micRecording && state.micRecorder) {
            state.micRecorder.stop();
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setStatus('Browser microphone recording is unavailable on this connection. Upload an MP3/WAV file instead.', 'warn');
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const recorder = new MediaRecorder(stream);
            state.micChunks = [];
            state.micRecorder = recorder;
            state.micRecording = true;
            recordMicBtn.textContent = '⏹ Stop Mic';
            recordMicBtn.classList.add('bg-rose-600', 'text-white');

            recorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) state.micChunks.push(e.data);
            };
            recorder.onstop = async () => {
                stream.getTracks().forEach((tr) => tr.stop());
                state.micRecording = false;
                recordMicBtn.textContent = '🎤 Record Mic';
                recordMicBtn.classList.remove('bg-rose-600', 'text-white');

                const blob = new Blob(state.micChunks, { type: recorder.mimeType || 'audio/webm' });
                const arr = await blob.arrayBuffer();
                const audioCtx = ensureAudioContext();
                state.masterVoiceBuffer = await audioCtx.decodeAudioData(arr);
                state.slideVoiceBuffers = [];
                updateVoiceBadge(`Mic Recording (${state.masterVoiceBuffer.duration.toFixed(1)}s)`, true);
                setStatus('Mic voiceover loaded! Press Play Preview to listen with the visuals.', 'success');
            };
            recorder.start();
            setStatus('Recording microphone... Speak your cricket commentary, then click Stop Mic.', 'info');
        } catch (e) {
            setStatus('Microphone permission denied or unavailable.', 'error');
        }
    }

    function pickBestRecorderMime() {
        const candidates = [
            'video/mp4;codecs=avc1.42E01E,mp4a.40.2',
            'video/webm;codecs=vp9,opus',
            'video/webm;codecs=vp8,opus',
            'video/webm',
        ];
        for (const m of candidates) {
            if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(m)) {
                return m;
            }
        }
        return '';
    }

    async function exportReelVideo() {
        if (state.recording) return;
        if (state.playing) {
            state.playing = false;
            stopAudioPlayback();
            if (previewBtn) previewBtn.textContent = '▶ Play Preview';
        }

        if (typeof canvas.captureStream !== 'function' || typeof MediaRecorder === 'undefined') {
            setStatus('Your browser does not support HTML5 Canvas video recording. Try Chrome, Edge, or Brave.', 'error');
            return;
        }

        const timeline = getTimeline();
        state.recording = true;
        exportBtn.disabled = true;
        const origText = exportBtn.textContent;
        setStatus('Rendering Reel frames & mixing audio in real time... Keep this tab open.', 'info');

        try {
            renderAt(0);
            const canvasStream = canvas.captureStream(30);
            const tracks = [...canvasStream.getVideoTracks()];

            const audioCtx = ensureAudioContext();
            const hasAudio =
                state.beatStyle !== 'off' ||
                Boolean(state.customMusicBuffer) ||
                Boolean(state.masterVoiceBuffer) ||
                state.slideVoiceBuffers.some((v) => v && v.buffer);

            if (hasAudio) {
                const dest = audioCtx.createMediaStreamDestination();
                await scheduleAudioGraph(dest, 0);
                dest.stream.getAudioTracks().forEach((tr) => tracks.push(tr));
            }

            const combinedStream = new MediaStream(tracks);
            const mimeType = pickBestRecorderMime();
            const recorderOptions = { videoBitsPerSecond: 4500000 };
            if (mimeType) recorderOptions.mimeType = mimeType;

            const recorder = new MediaRecorder(combinedStream, recorderOptions);
            const chunks = [];
            recorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) chunks.push(e.data);
            };

            const stoppedPromise = new Promise((resolve) => {
                recorder.onstop = resolve;
            });

            recorder.start(250);

            const renderStart = performance.now();
            await new Promise((resolve) => {
                const step = (now) => {
                    const elapsed = (now - renderStart) / 1000;
                    state.playhead = Math.min(elapsed, timeline.total);
                    renderAt(state.playhead);

                    const pct = Math.min(99, Math.round((elapsed / timeline.total) * 100));
                    exportBtn.textContent = `⏺ Recording ${pct}%`;

                    if (elapsed >= timeline.total) {
                        resolve();
                        return;
                    }
                    requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            });

            recorder.stop();
            await stoppedPromise;
            stopAudioPlayback();
            combinedStream.getTracks().forEach((tr) => tr.stop());

            const rawType = recorder.mimeType || mimeType || 'video/webm';
            const rawBlob = new Blob(chunks, { type: rawType });

            let finalBlob = rawBlob;
            let finalExt = rawType.includes('mp4') ? 'mp4' : 'webm';

            // Try server-side FFmpeg conversion for universal Instagram/YouTube/WhatsApp MP4 compatibility
            if (state.convertMp4 && convertEndpoint) {
                exportBtn.textContent = '⚙️ Converting to MP4...';
                setStatus('Converting to Instagram & YouTube Shorts MP4 (H.264/AAC)...', 'info');
                const form = new FormData();
                form.append('video', rawBlob, `cricapp-reel.${finalExt}`);
                form.append('max_duration', String(Math.ceil(timeline.total + 2)));

                try {
                    const mp4Resp = await fetch(convertEndpoint, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        body: form,
                    });
                    if (mp4Resp.ok && (mp4Resp.headers.get('Content-Type') || '').includes('video/mp4')) {
                        finalBlob = await mp4Resp.blob();
                        finalExt = 'mp4';
                        setStatus('Reel exported as universal MP4 (H.264/AAC)! Ready to upload to Instagram & YouTube.', 'success');
                    } else {
                        const errJson = await mp4Resp.json().catch(() => ({}));
                        setStatus(
                            (errJson.message || 'Server MP4 converter unavailable here.') +
                                ` Saved native .${finalExt.toUpperCase()} video directly.`,
                            'warn'
                        );
                    }
                } catch (_) {
                    setStatus(`MP4 server offline — saved high-quality .${finalExt.toUpperCase()} directly from browser.`, 'warn');
                }
            } else {
                setStatus(`Reel rendered successfully (.${finalExt.toUpperCase()})!`, 'success');
            }

            const blobUrl = URL.createObjectURL(finalBlob);
            state.objectUrls.push(blobUrl);
            const fileName = `cricapp-reel-${Date.now()}.${finalExt}`;

            if (exportVideoPreview && exportResultBox && exportDownloadLink) {
                exportVideoPreview.src = blobUrl;
                exportDownloadLink.href = blobUrl;
                exportDownloadLink.download = fileName;
                exportDownloadLink.textContent = `⬇️ Download ${finalExt.toUpperCase()} (${(finalBlob.size / (1024 * 1024)).toFixed(1)} MB)`;
                exportResultBox.classList.remove('hidden');
            }

            // Trigger immediate download for convenience
            const autoA = document.createElement('a');
            autoA.href = blobUrl;
            autoA.download = fileName;
            document.body.appendChild(autoA);
            autoA.click();
            autoA.remove();
        } catch (err) {
            console.error(err);
            setStatus(`Export failed: ${err.message || 'Unknown error'}`, 'error');
        } finally {
            state.recording = false;
            state.playhead = 0;
            renderAt(0);
            exportBtn.disabled = false;
            exportBtn.textContent = origText;
        }
    }

    function renderSlidesEditor() {
        if (!slidesListEl) return;

        const curatedOptions = curatedImages
            .map(
                (c) =>
                    `<button type="button" data-action="pick-curated" data-url="${escapeHtml(c.url)}" class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[10px] font-bold text-slate-300 border border-white/10 transition">${escapeHtml(c.label)}</button>`
            )
            .join('');

        slidesListEl.innerHTML = state.slides
            .map((slide, idx) => {
                const isSelected = idx === state.selectedSlideIndex;
                return `
                <div class="rounded-2xl border ${isSelected ? 'border-emerald-500/50 bg-slate-900/90' : 'border-white/10 bg-slate-900/55'} p-4 space-y-3 transition" data-slide-idx="${idx}">
                    <div class="flex items-center justify-between gap-2">
                        <button type="button" data-action="focus-slide" class="flex items-center gap-2 text-left">
                            <span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-300 text-xs font-black flex items-center justify-center border border-emerald-500/30">${idx + 1}</span>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-200">${escapeHtml(slide.badge || 'Slide')}</span>
                        </button>
                        <div class="flex items-center gap-2">
                            <label class="text-[11px] text-slate-400 font-bold flex items-center gap-1">
                                Sec:
                                <input type="number" step="0.5" min="2" max="25" data-field="duration" value="${escapeHtml(slide.duration)}" class="w-14 px-2 py-1 rounded-lg bg-slate-950 border border-white/10 text-xs text-white font-bold">
                            </label>
                            ${state.slides.length > 2 ? `<button type="button" data-action="remove-slide" class="px-2 py-1 rounded-lg bg-rose-500/15 hover:bg-rose-500/30 text-rose-300 text-[11px] font-bold">Remove</button>` : ''}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Top Badge</label>
                            <input type="text" data-field="badge" value="${escapeHtml(slide.badge)}" class="w-full px-3 py-1.5 rounded-xl bg-slate-950 border border-white/10 text-xs font-bold text-white">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Background Image URL or Upload</label>
                            <div class="flex items-center gap-1.5">
                                <input type="text" data-field="image" value="${escapeHtml(slide.image)}" class="w-full px-2.5 py-1.5 rounded-xl bg-slate-950 border border-white/10 text-xs text-slate-300">
                                <label class="shrink-0 px-2.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-white cursor-pointer border border-white/10">
                                    Upload
                                    <input type="file" accept="image/*" data-action="upload-slide-image" class="hidden">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Bold Screen Headline</label>
                        <input type="text" data-field="headline" value="${escapeHtml(slide.headline)}" class="w-full px-3 py-1.5 rounded-xl bg-slate-950 border border-white/10 text-xs font-bold text-white">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Supporting Subtext</label>
                        <input type="text" data-field="subtext" value="${escapeHtml(slide.subtext)}" class="w-full px-3 py-1.5 rounded-xl bg-slate-950 border border-white/10 text-xs text-slate-200">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Spoken Voiceover & Caption Text</label>
                        <textarea rows="2" data-field="narration" class="w-full px-3 py-1.5 rounded-xl bg-slate-950 border border-white/10 text-xs text-slate-200">${escapeHtml(slide.narration)}</textarea>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase mr-1">Quick Pics:</span>
                        ${curatedOptions}
                    </div>
                </div>`;
            })
            .join('');
    }

    // Delegate Slide Card Inputs & Buttons
    slidesListEl?.addEventListener('input', (e) => {
        const card = e.target.closest('[data-slide-idx]');
        if (!card) return;
        const idx = Number(card.getAttribute('data-slide-idx'));
        const field = e.target.getAttribute('data-field');
        if (!field || !state.slides[idx]) return;

        state.selectedSlideIndex = idx;
        state.slides[idx][field] = field === 'duration' ? Number(e.target.value) || 5.0 : e.target.value;

        const timeline = getTimeline();
        const seg = timeline.segments[idx];
        if (seg && !state.playing && !state.recording) {
            state.playhead = seg.start + 0.5;
            renderAt(state.playhead);
        }
    });

    slidesListEl?.addEventListener('change', (e) => {
        if (e.target.getAttribute('data-action') !== 'upload-slide-image') return;
        const file = e.target.files?.[0];
        if (!file) return;
        const card = e.target.closest('[data-slide-idx]');
        const idx = Number(card?.getAttribute('data-slide-idx'));
        if (!state.slides[idx]) return;

        const blobUrl = URL.createObjectURL(file);
        state.objectUrls.push(blobUrl);
        state.slides[idx].image = blobUrl;
        state.selectedSlideIndex = idx;
        renderSlidesEditor();
        const timeline = getTimeline();
        state.playhead = (timeline.segments[idx]?.start || 0) + 0.4;
        renderAt(state.playhead);
    });

    slidesListEl?.addEventListener('click', (e) => {
        const actionEl = e.target.closest('[data-action]');
        if (!actionEl) return;
        const card = actionEl.closest('[data-slide-idx]');
        const idx = Number(card?.getAttribute('data-slide-idx'));
        if (!state.slides[idx]) return;

        const action = actionEl.getAttribute('data-action');
        if (action === 'focus-slide') {
            state.selectedSlideIndex = idx;
            const timeline = getTimeline();
            state.playhead = (timeline.segments[idx]?.start || 0) + 0.4;
            renderSlidesEditor();
            renderAt(state.playhead);
        } else if (action === 'remove-slide') {
            state.slides.splice(idx, 1);
            state.slideVoiceBuffers.splice(idx, 1);
            state.selectedSlideIndex = Math.max(0, Math.min(state.selectedSlideIndex, state.slides.length - 1));
            renderSlidesEditor();
            renderAt(0);
        } else if (action === 'pick-curated') {
            const url = actionEl.getAttribute('data-url') || '';
            state.slides[idx].image = url;
            state.selectedSlideIndex = idx;
            renderSlidesEditor();
            const timeline = getTimeline();
            state.playhead = (timeline.segments[idx]?.start || 0) + 0.4;
            renderAt(state.playhead);
        }
    });

    addSlideBtn?.addEventListener('click', () => {
        const fallbackImg = curatedImages[state.slides.length % Math.max(1, curatedImages.length)]?.url || '';
        state.slides.push({
            badge: `SLIDE ${state.slides.length + 1}`,
            headline: 'Add Key Cricket Moment Headline',
            subtext: 'Short match insight or batting/bowling stat line.',
            narration: 'Here is another key highlight from today\'s cricket action.',
            image: fallbackImg,
            duration: 5.0,
        });
        state.selectedSlideIndex = state.slides.length - 1;
        renderSlidesEditor();
        const timeline = getTimeline();
        state.playhead = (timeline.segments[state.selectedSlideIndex]?.start || 0) + 0.4;
        renderAt(state.playhead);
    });

    themeSelect?.addEventListener('change', () => {
        state.theme = themeSelect.value;
        renderAt(state.playhead);
    });

    beatSelect?.addEventListener('change', () => {
        state.beatStyle = beatSelect.value;
    });

    musicVolInput?.addEventListener('input', () => {
        state.musicVolume = Number(musicVolInput.value) / 100;
    });

    captionsToggle?.addEventListener('change', () => {
        state.showCaptions = captionsToggle.checked;
        renderAt(state.playhead);
    });

    convertMp4Toggle?.addEventListener('change', () => {
        state.convertMp4 = convertMp4Toggle.checked;
    });

    progressRange?.addEventListener('input', () => {
        if (state.playing) {
            state.playing = false;
            stopAudioPlayback();
            if (previewBtn) previewBtn.textContent = '▶ Play Preview';
        }
        state.playhead = Number(progressRange.value) || 0;
        renderAt(state.playhead);
    });

    generateTtsBtn?.addEventListener('click', generateAllSlidesTts);
    recordMicBtn?.addEventListener('click', toggleMicRecording);
    previewBtn?.addEventListener('click', togglePreviewPlayback);
    exportBtn?.addEventListener('click', exportReelVideo);

    clearVoiceBtn?.addEventListener('click', () => {
        state.slideVoiceBuffers = [];
        state.masterVoiceBuffer = null;
        updateVoiceBadge('No Voiceover', false);
        setStatus('Voiceover cleared. Background beat / silent captions active.', 'info');
    });

    uploadVoiceInput?.addEventListener('change', async () => {
        const file = uploadVoiceInput.files?.[0];
        if (!file) return;
        try {
            const arr = await file.arrayBuffer();
            const audioCtx = ensureAudioContext();
            state.masterVoiceBuffer = await audioCtx.decodeAudioData(arr);
            state.slideVoiceBuffers = [];
            updateVoiceBadge(`Uploaded (${state.masterVoiceBuffer.duration.toFixed(1)}s)`, true);
            setStatus(`Loaded custom voice file "${file.name}".`, 'success');
        } catch (_) {
            setStatus('Could not decode uploaded audio file. Please try MP3 or WAV.', 'error');
        }
    });

    uploadMusicInput?.addEventListener('change', async () => {
        const file = uploadMusicInput.files?.[0];
        if (!file) return;
        try {
            const arr = await file.arrayBuffer();
            const audioCtx = ensureAudioContext();
            state.customMusicBuffer = await audioCtx.decodeAudioData(arr);
            setStatus(`Custom music track "${file.name}" loaded!`, 'success');
        } catch (_) {
            setStatus('Could not decode background music file.', 'error');
        }
    });

    loadArticleBtn?.addEventListener('click', () => {
        const artId = articlePicker?.value;
        if (!artId) return;
        window.location.href = `${studioBaseUrl}?article_id=${encodeURIComponent(artId)}`;
    });

    copyCaptionBtn?.addEventListener('click', async () => {
        if (!captionTextarea) return;
        try {
            await navigator.clipboard.writeText(captionTextarea.value);
            copyCaptionBtn.textContent = '✅ Copied!';
            setTimeout(() => {
                copyCaptionBtn.textContent = '📋 Copy Caption & Tags';
            }, 2000);
        } catch (_) {
            captionTextarea.select();
        }
    });

    // Initial render
    renderSlidesEditor();
    renderAt(0.4);
}
