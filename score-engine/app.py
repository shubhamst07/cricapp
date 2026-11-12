import re
import html
import time
import json
import asyncio
from typing import List, Optional, Dict, Any

import httpx
from bs4 import BeautifulSoup
from fastapi import FastAPI, Query, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.openapi.docs import get_swagger_ui_html
from fastapi.responses import (
    JSONResponse,
    PlainTextResponse,
    HTMLResponse,
)
from pydantic import BaseModel, field_validator
from starlette.exceptions import HTTPException as StarletteHTTPException


NOT_FOUND = "score not found"
REQUEST_TIMEOUT = "request timeout"
INVALID_MATCH_ID = "invalid score id"

# In-memory simple cache
_CACHE: Dict[str, Dict[str, Any]] = {}

def get_cached(key: str, ttl: int):
    entry = _CACHE.get(key)
    if entry and (time.time() - entry["ts"]) < ttl:
        return entry["data"]
    return None

def set_cached(key: str, data: Any):
    _CACHE[key] = {"ts": time.time(), "data": data}


class APIError(Exception):
    def __init__(self, status_code: int, message: str):
        self.status_code = status_code
        self.message = message


class Batsman(BaseModel):
    name: str = NOT_FOUND
    score: str = NOT_FOUND


class Bowler(BaseModel):
    name: str = NOT_FOUND


class ScoreResponse(BaseModel):
    status: str
    title: str
    score: str
    current_batsmen: List[Batsman]
    current_bowler: Bowler
    player_of_the_match: Optional[Dict[str, Any]] = None
    status_text: Optional[str] = None


class MatchValidator(BaseModel):
    score: str

    @field_validator("score")
    @classmethod
    def validate_match_id(cls, value: str) -> str:
        value = value.strip()
        if not value:
            raise ValueError(INVALID_MATCH_ID)
        if not value.isdigit():
            raise ValueError("score id must contain digits only")
        if len(value) < 4:
            raise ValueError("score id must be at least 4 digits")
        if len(value) > 20:
            raise ValueError("score id too long")
        return value


app = FastAPI(
    title="Score API",
    version="0.0.2",
    description="Live Cricket Score & Matches JSON API",
    docs_url=None,
    redoc_url=None
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["GET"],
    allow_headers=["*"],
)


@app.middleware("http")
async def security_headers(request: Request, call_next):
    response = await call_next(request)
    response.headers["Cache-Control"] = (
        "no-store, no-cache, must-revalidate, "
        "proxy-revalidate, max-age=0"
    )
    response.headers["Pragma"] = "no-cache"
    response.headers["Expires"] = "0"
    response.headers["Surrogate-Control"] = "no-store"
    response.headers["X-Content-Type-Options"] = "nosniff"
    response.headers["X-Frame-Options"] = "DENY"
    response.headers["Referrer-Policy"] = "no-referrer"
    response.headers["X-Robots-Tag"] = "noindex, nofollow"
    return response


class ScoreService:
    HEADERS = {
        "User-Agent": (
            "Mozilla/5.0 "
            "(X11; Linux x86_64) "
            "AppleWebKit/537.36 "
            "(KHTML, like Gecko) "
            "Chrome/146.0.0.0 "
            "Safari/537.36"
        ),
        "Referer": "https://www.cricbuzz.com/",
        "Origin": "https://www.cricbuzz.com",
        "Cache-Control": "no-cache, no-store, max-age=0",
        "Pragma": "no-cache",
        "Expires": "0",
        "Connection": "close",
        "Accept": (
            "text/html,"
            "application/xhtml+xml,"
            "application/xml;q=0.9,"
            "*/*;q=0.8"
        ),
        "Accept-Language": "en-US,en;q=0.9",
    }

    @staticmethod
    def clean(text: str) -> str:
        if not text:
            return NOT_FOUND
        cleaned = " ".join(text.split())
        cleaned = re.sub(r"\s*View match performance.*", "", cleaned, flags=re.IGNORECASE)
        cleaned = re.sub(r"\s*View profile.*", "", cleaned, flags=re.IGNORECASE)
        return html.escape(cleaned.strip())

    @classmethod
    def default_batsmen(cls) -> List[Batsman]:
        return [Batsman(), Batsman()]

    @classmethod
    def format_tree(cls, data: ScoreResponse) -> str:
        batsmen_lines = "\n".join(
            f"│   ├── {player.name} : {player.score}"
            for player in data.current_batsmen
        )
        return (
            "🏏 Live Score\n"
            "│\n"
            f"├── Match    : {data.title}\n"
            f"├── Score    : {data.score}\n"
            f"├── Bowler   : {data.current_bowler.name}\n"
            "├── Batsmen\n"
            f"{batsmen_lines}"
        )

    @classmethod
    def extract_player_of_the_match(cls, html_content: str) -> Optional[Dict[str, Any]]:
        idx = html_content.find("PLAYER OF THE MATCH")
        if idx == -1:
            idx = html_content.find("Player of the Match")
        if idx == -1:
            return None

        chunk = html_content[idx:idx + 3000]
        # Regex 1: Cricbuzz player card with image, name, stats
        m = re.search(r'PLAYER\s+OF\s+THE\s+MATCH.*?<img[^>]*alt=\"([^\"]+)\"[^>]*src=\"([^\"]+)\".*?<span[^>]*>([^<]+)</span>.*?<p[^>]*>([^<]+)</p>', chunk, re.DOTALL | re.IGNORECASE)
        if m:
            alt, img, name, stats = m.groups()
            return {
                "name": html.unescape(name.strip()),
                "stats": html.unescape(stats.strip()),
                "image": html.unescape(img.strip()),
                "award": "Player of the Match"
            }

        # Regex 2: Broad fallback
        name_m = re.search(r'<span[^>]*class=\"hover:underline\"[^>]*>([^<]+)</span>', chunk) or re.search(r'<span[^>]*>([^<]{2,40})</span>', chunk)
        stats_m = re.search(r'<p[^>]*class=\"[^\"]*text-cbTxtSec[^\"]*\"[^>]*>([^<]+)</p>', chunk)
        img_m = re.search(r'<img[^>]*src=\"([^\"]+)\"', chunk)
        if name_m:
            return {
                "name": html.unescape(name_m.group(1).strip()),
                "stats": html.unescape(stats_m.group(1).strip()) if stats_m else "",
                "image": html.unescape(img_m.group(1).strip()) if img_m else "",
                "award": "Player of the Match"
            }
        return None

    @classmethod
    async def fetch_score(cls, match_id: str) -> ScoreResponse:
        cached = get_cached(f"score_{match_id}", ttl=5)
        if cached:
            return cached

        try:
            url = f"https://www.cricbuzz.com/live-cricket-scores/{match_id}?_={time.time_ns()}"

            async with httpx.AsyncClient(timeout=10.0, follow_redirects=True) as client:
                response = await client.get(url, headers=cls.HEADERS)
                response.raise_for_status()

            soup = BeautifulSoup(response.text, "lxml")

            title = cls.clean(
                re.sub(
                    r"^Cricket commentary\s*\|\s*",
                    "",
                    soup.title.get_text(strip=True) if soup.title else NOT_FOUND,
                    flags=re.IGNORECASE
                )
            )

            og_tag = soup.find("meta", property="og:title")
            og_title = og_tag.get("content", "") if og_tag else ""

            score = NOT_FOUND
            score_match = re.search(r"([A-Z]{2,4})\s+(\d+)/(\d+)\s*\(([\d.]+)\)", og_title)
            if score_match:
                team, runs, wickets, overs = score_match.groups()
                score = f"{team} {runs}/{wickets} ({overs})"

            batsmen = []
            batsman_match = re.search(r"\((.*?)\)\s*\|", og_title)
            if batsman_match:
                players = re.findall(r"([A-Za-z\s.'-]+)\s+(\d+\(\d+\))", batsman_match.group(1))
                batsmen = [
                    Batsman(name=cls.clean(name), score=cls.clean(score_value))
                    for name, score_value in players[:2]
                ]

            if len(batsmen) < 2:
                batsmen = cls.default_batsmen()

            page_text = soup.get_text(" ", strip=True)
            bowler_match = re.search(r"Bowler.*?([A-Za-z.'\- ]+?)\s+\d+\s+\d+", page_text, re.IGNORECASE)
            bowler_name = cls.clean(bowler_match.group(1)) if bowler_match else NOT_FOUND

            pom = cls.extract_player_of_the_match(response.text)

            page_status = None
            stts_el = soup.find(class_=lambda c: c and any(k in c for k in ["cb-min-stts", "cb-text-inprogress", "cb-text-complete", "cb-text-mom", "cb-text-live"]))
            if stts_el:
                page_status = cls.clean(stts_el.get_text(" ", strip=True))

            res = ScoreResponse(
                status="success",
                title=title,
                score=score,
                current_batsmen=batsmen,
                current_bowler=Bowler(name=bowler_name),
                player_of_the_match=pom,
                status_text=page_status
            )
            set_cached(f"score_{match_id}", res)
            return res

        except httpx.TimeoutException:
            raise APIError(408, REQUEST_TIMEOUT)
        except httpx.HTTPStatusError:
            raise APIError(404, "score data unavailable")
        except Exception:
            raise APIError(500, "failed to process score data")

    @classmethod
    def parse_rsc_matches(cls, html_text: str) -> List[Dict[str, Any]]:
        marker = 'matchesList'
        pos = 0
        results = []
        seen = set()
        while True:
            pos = html_text.find(marker, pos)
            if pos == -1:
                break

            arr_start = html_text.find('[', pos)
            if arr_start == -1:
                pos += len(marker)
                continue

            depth = 0
            arr_end = -1
            for i in range(arr_start, min(len(html_text), arr_start + 250000)):
                if html_text[i] == '[':
                    depth += 1
                elif html_text[i] == ']':
                    depth -= 1
                    if depth == 0:
                        arr_end = i
                        break
            if arr_end != -1:
                raw_json = html_text[arr_start:arr_end + 1]
                cleaned = raw_json.replace('\\\\\\"', '"').replace('\\\"', '"').replace('\\\\\\\\', '\\')
                try:
                    m_list = json.loads(cleaned)
                    for item in m_list:
                        match_obj = item.get('match') or item
                        info = match_obj.get('matchInfo', {})
                        m_id = str(info.get('matchId', ''))
                        if m_id and m_id not in seen:
                            seen.add(m_id)
                            t1 = info.get('team1', {}).get('teamName', 'Team 1')
                            t2 = info.get('team2', {}).get('teamName', 'Team 2')
                            match_score = match_obj.get('matchScore')
                            scores = []
                            if match_score:
                                for team_key, team_name in [('team1Score', t1), ('team2Score', t2)]:
                                    t_data = match_score.get(team_key)
                                    if not t_data:
                                        continue
                                    for inngs_key in ['inngs1', 'inngs2']:
                                        inn = t_data.get(inngs_key)
                                        if not inn:
                                            continue
                                        runs = inn.get('runs', 0)
                                        raw_w = inn.get('wickets')
                                        is_all_out = bool(inn.get('isAllOut'))
                                        if is_all_out:
                                            wickets = 10
                                        elif raw_w is not None:
                                            wickets = int(raw_w)
                                        else:
                                            wickets = 0
                                        overs = str(inn.get('overs', '0'))
                                        scores.append({
                                            'inning': f"{team_name} Innings" if inngs_key == 'inngs1' else f"{team_name} 2nd Innings",
                                            'batting_team': team_name,
                                            'runs': runs,
                                            'r': runs,
                                            'wickets': wickets,
                                            'w': wickets,
                                            'overs': overs,
                                            'o': overs,
                                            'display_score': f"{runs}/{wickets}" if (wickets is not None and wickets > 0) else str(runs)
                                        })

                            state = str(info.get('state', '')).lower()
                            status = info.get('status', '') or info.get('shortStatus', '') or info.get('stateTitle', '')
                            match_ended = state in ['complete', 'result', 'finished']
                            match_started = state in ['in progress', 'complete', 'toss'] or bool(scores)
                            series_name = info.get('seriesName', 'Other Matches')
                            match_desc = info.get('matchDesc', 'Match')

                            results.append({
                                'id': m_id,
                                'name': f"{t1} vs {t2}, {match_desc}",
                                'matchType': match_desc,
                                'venue': f"{info.get('venueInfo', {}).get('ground', '')}, {info.get('venueInfo', {}).get('city', '')}".strip(', '),
                                'status_text': status,
                                'series': series_name,
                                'series_name': series_name,
                                'teams': [t1, t2],
                                'score': scores if scores else None,
                                'matchStarted': match_started,
                                'matchEnded': match_ended,
                                'href': f"/live-cricket-scores/{m_id}"
                            })
                except Exception as e:
                    pass
            pos = arr_start + 1
        return results

    @classmethod
    def parse_cards_from_html(cls, html_text: str) -> List[Dict[str, Any]]:
        soup = BeautifulSoup(html_text, "lxml")
        cards = soup.find_all("a", href=lambda h: h and re.match(r"^/live-cricket-scores/(\d+)/", h))
        if not cards:
            cards = soup.find_all("a", class_=lambda c: c and "bg-cbWhite" in c)
        parsed = []

        for card in cards:
            # Skip top navbar / header ticker items
            cls_str = " ".join(card.get("class", []))
            if "max-w-[160px]" in cls_str or ("items-center" in cls_str and "hover:bg-[#444]" in cls_str):
                continue

            href = card.get("href", "")
            m = re.match(r"^/live-cricket-scores/(\d+)/([\w-]+)$", href)
            if not m:
                continue
            m_id = m.group(1)
            title = card.get("title", "").strip()

            hdr = card.find_previous(class_=lambda cls: cls and "bg-cbGrpHdrBkg" in cls)
            series_name = hdr.get_text(" ", strip=True) if hdr else ""
            series_name = " ".join(series_name.split()).strip()
            if not series_name:
                series_name = "Other Matches"

            children = [c for c in card.children if hasattr(c, "find_all")]

            meta_info = children[0].get_text(" ", strip=True) if len(children) > 0 else ""
            status_text = ""
            for el in card.find_all(["div", "span", "p"]):
                el_cls = " ".join(el.get("class", []))
                if any(k in el_cls for k in ["cb-text-complete", "cb-text-live", "cb-text-preview", "cb-hm-scg-status", "text-cbTextSec"]):
                    txt = el.get_text(" ", strip=True)
                    if txt and txt != title and txt != series_name:
                        status_text = txt
                        break
            if not status_text and len(children) > 2:
                status_text = children[2].get_text(" ", strip=True)
            if not status_text:
                for el in card.find_all(["div", "span", "p"]):
                    txt = el.get_text(" ", strip=True)
                    if any(w in txt.lower() for w in ["won by", "won the", " beat ", "tied", "drawn", "abandon", "no result", "opt to", "elected to", "need ", "trail", "lead", "day ", "stump", "delay", "rain"]):
                        status_text = txt
                        break

            teams = []
            scores = []
            team_rows = []
            if len(children) > 1 and hasattr(children[1], "find_all"):
                team_rows = children[1].find_all("div", class_=lambda c: c and "justify-between" in c)
            if not team_rows:
                team_rows = card.find_all("div", class_=lambda c: c and ("justify-between" in c or "cb-ovr-flo" in c or "cb-hm-scg" in c))

            for row in team_rows:
                spans = row.find_all("span")
                team_name = spans[0].get_text(strip=True) if len(spans) > 0 else ""
                team_short = spans[1].get_text(strip=True) if len(spans) > 1 else team_name
                score_str = spans[2].get_text(strip=True) if len(spans) > 2 else ""

                if team_name:
                    teams.append(team_name)

                if score_str:
                    # Handle multi-innings e.g. "113 & 516" or "317 & 187-9" (Test matches)
                    if "&" in score_str:
                        sub_parts = score_str.split("&")
                        for s_idx, sp in enumerate(sub_parts):
                            sp = sp.strip()
                            sm = re.search(r"(\d+)(?:[-/](\d+))?\s*(?:\(([\d.]+)\s*(?:ov)?\))?", sp)
                            if sm:
                                runs = int(sm.group(1))
                                raw_w = sm.group(2)
                                overs = sm.group(3) if (sm.group(3) and sm.group(3) != "0" and sm.group(3) != "0.0") else None
                                wickets = int(raw_w) if raw_w is not None else 10
                                scores.append({
                                    "inning": f"{team_name} Inn {s_idx + 1}" if len(sub_parts) > 1 else f"{team_name} Inning 1",
                                    "batting_team": team_name,
                                    "runs": runs,
                                    "r": runs,
                                    "wickets": wickets,
                                    "w": wickets,
                                    "overs": overs,
                                    "o": overs,
                                    "display_score": f"{runs}/{wickets}"
                                })
                    else:
                        sm = re.search(r"(\d+)(?:[-/](\d+))?\s*(?:\(([\d.]+)\s*(?:ov)?\))?", score_str)
                        if sm:
                            runs = int(sm.group(1))
                            raw_w = sm.group(2)
                            overs = sm.group(3) if (sm.group(3) and sm.group(3) != "0" and sm.group(3) != "0.0") else None
                            # Cricbuzz omits wickets ONLY when a team is all out (10 wickets)!
                            # e.g. "239 (50 Ovs)" or "174 (43.4 Ovs)" -> 10 wickets!
                            wickets = int(raw_w) if raw_w is not None else 10
                            scores.append({
                                "inning": f"{team_name} Inning 1",
                                "batting_team": team_name,
                                "runs": runs,
                                "r": runs,
                                "wickets": wickets,
                                "w": wickets,
                                "overs": overs,
                                "o": overs,
                                "display_score": f"{runs}/{wickets}"
                            })

            match_type = ""
            venue = ""
            if "•" in meta_info:
                parts = meta_info.split("•", 1)
                match_type = parts[0].strip()
                venue = parts[1].strip()
            else:
                venue = meta_info

            # Match completed detection (must not match toss events like "won the toss", "opt to bat", or ongoing test events)
            st_lower = status_text.lower()
            is_abandoned = any(w in st_lower for w in ["abandon", "cancel", "no result"])
            is_toss = not is_abandoned and (any(w in st_lower for w in ["opt to", "elected to", "chose to", "choose to"]) or ("toss" in st_lower and "abandon" not in st_lower))
            is_test_ongoing = not is_abandoned and any(w in st_lower for w in ["stump", "trail by", "lead by", "day 1", "day 2", "day 3", "day 4", "tea", "lunch"]) and not any(w in st_lower for w in ["won by", "won the match", " beat ", "drawn", "tied", "abandon"])
            is_completed = is_abandoned or (not is_toss and not is_test_ongoing and any(w in st_lower for w in ["won by", "won the match", " beat ", "tied", "drawn", "conceded", "match ended"]))

            # Match started detection
            match_started = is_toss or is_test_ongoing or bool(scores) or is_completed

            # Normalize status_text for matches with active score or upcoming matches
            if is_completed:
                # Keep completed status
                pass
            elif is_toss or is_test_ongoing:
                # Keep rich toss or test status text (LIVE match)
                pass
            elif scores:
                if not status_text or status_text.lower() in ["scheduled", "match scheduled", "upcoming"]:
                    status_text = "In Progress"
            elif not scores:
                if not status_text or (teams and all(t.lower() in status_text.lower() for t in teams)):
                    status_text = "Match Scheduled"

            parsed.append({
                "id": m_id,
                "name": title,
                "matchType": match_type,
                "venue": venue,
                "status_text": status_text,
                "series": series_name,
                "series_name": series_name,
                "teams": teams,
                "score": scores if scores else None,
                "matchStarted": match_started,
                "matchEnded": is_completed,
                "href": href
            })

        return parsed

    @classmethod
    async def fetch_all_matches(cls) -> List[Dict[str, Any]]:
        cached = get_cached("all_matches", ttl=30)
        if cached:
            return cached

        try:
            url_live = f"https://www.cricbuzz.com/cricket-match/live-scores?_={time.time_ns()}"
            url_recent = f"https://www.cricbuzz.com/cricket-match/live-scores/recent-matches?_={time.time_ns()}"
            url_upcoming = f"https://www.cricbuzz.com/cricket-match/live-scores/upcoming-matches?_={time.time_ns()}"
            sched_url = f"https://www.cricbuzz.com/cricket-schedule/upcoming-series/international?_={time.time_ns()}"

            async with httpx.AsyncClient(timeout=12.0, follow_redirects=True) as client:
                resps = await asyncio.gather(
                    client.get(url_live, headers=cls.HEADERS),
                    client.get(url_recent, headers=cls.HEADERS),
                    client.get(url_upcoming, headers=cls.HEADERS),
                    client.get(sched_url, headers=cls.HEADERS),
                    return_exceptions=True
                )

            parsed = []
            parsed_ids = set()

            sources = [
                (resps[0] if len(resps) > 0 else None, "live"),
                (resps[1] if len(resps) > 1 else None, "recent"),
                (resps[2] if len(resps) > 2 else None, "upcoming")
            ]

            for resp, page_type in sources:
                if isinstance(resp, httpx.Response) and resp.status_code == 200:
                    # 1. Parse high-fidelity RSC matches first (contains full series, state, and real-time scores)
                    rsc_matches = cls.parse_rsc_matches(resp.text)
                    for m in rsc_matches:
                        st = (m.get("status_text") or "").lower()
                        is_test_ongoing = any(w in st for w in ["stump", "trail by", "lead by", "day 1", "day 2", "day 3", "day 4", "tea", "lunch"]) and not any(w in st for w in ["won by", "won the match", " beat ", "drawn", "tied", "abandoned"])
                        is_chasing = any(w in st for w in ["need ", "require ", "target "])
                        is_toss = any(w in st for w in ["opt to", "elected to", "chose to", "choose to"]) or ("toss" in st and "abandon" not in st)
                        is_live = is_test_ongoing or is_chasing or is_toss or ("in progress" in st) or ("rain" in st) or ("delay" in st)

                        if is_live:
                            m["matchEnded"] = False
                            m["matchStarted"] = True
                        elif page_type == "recent":
                            m["matchEnded"] = True
                            m["matchStarted"] = True
                            if not m.get("status_text") or m["status_text"].lower() == "in progress":
                                m["status_text"] = "Match Completed"
                        elif page_type == "upcoming":
                            m["matchEnded"] = False
                            m["matchStarted"] = False

                        if m["id"] not in parsed_ids:
                            parsed_ids.add(m["id"])
                            parsed.append(m)
                        else:
                            for idx, existing in enumerate(parsed):
                                if existing.get("id") == m["id"]:
                                    comb_st = (existing.get("status_text", "") + " " + m.get("status_text", "")).lower()
                                    is_comb_abandoned = any(w in comb_st for w in ["abandon", "cancel", "no result"])
                                    is_comb_test_ongoing = not is_comb_abandoned and any(w in comb_st for w in ["stump", "trail by", "lead by", "day 1", "day 2", "day 3", "day 4", "tea", "lunch"]) and not any(w in comb_st for w in ["won by", "won the match", " beat ", "drawn", "tied", "abandon"])
                                    is_comb_toss = not is_comb_abandoned and (any(w in comb_st for w in ["opt to", "elected to", "chose to", "choose to"]) or ("toss" in comb_st and "abandon" not in comb_st))
                                    is_comb_chasing = any(w in comb_st for w in ["need ", "require ", "target "])
                                    is_comb_live = is_comb_test_ongoing or is_comb_toss or is_comb_chasing or ("in progress" in comb_st) or ("rain" in comb_st) or ("delay" in comb_st)

                                    if is_comb_live:
                                        existing["matchEnded"] = False
                                        existing["matchStarted"] = True
                                    elif page_type == "recent":
                                        existing["matchEnded"] = True
                                        existing["matchStarted"] = True
                                        if not existing.get("status_text") or existing["status_text"].lower() == "in progress":
                                            existing["status_text"] = m.get("status_text") or "Match Completed"
                                    elif page_type == "upcoming" and not existing.get("score") and not is_comb_toss and not is_comb_test_ongoing:
                                        existing["matchEnded"] = False
                                        existing["matchStarted"] = False

                                    if m.get("score"):
                                        existing["score"] = m["score"]
                                    if m.get("series_name") and m["series_name"] != "Other Matches":
                                        existing["series"] = m["series_name"]
                                        existing["series_name"] = m["series_name"]
                                    new_st = (m.get("status_text") or "").strip()
                                    old_st = (existing.get("status_text") or "").strip()
                                    is_old_rich = any(w in old_st.lower() for w in ["opt to", "elected to", "chose to", "toss", "stump", "trail", "lead", "day ", "won by", "beat", "innings break", "need ", "require ", "target "])
                                    is_new_generic = new_st.lower() in ["match scheduled", "scheduled", "upcoming", "in progress", ""]
                                    if not (is_old_rich and is_new_generic):
                                        if new_st:
                                            existing["status_text"] = new_st
                                    if m.get("teams") and not existing.get("teams"):
                                        existing["teams"] = m["teams"]
                                    break

                    # 2. Parse traditional HTML cards
                    matches = cls.parse_cards_from_html(resp.text)
                    for m in matches:
                        st = (m.get("status_text") or "").lower()
                        is_test_ongoing = any(w in st for w in ["stump", "trail by", "lead by", "day 1", "day 2", "day 3", "day 4", "tea", "lunch"]) and not any(w in st for w in ["won by", "won the match", " beat ", "drawn", "tied", "abandoned"])
                        if page_type == "recent":
                            if not is_test_ongoing:
                                m["matchEnded"] = True
                                m["matchStarted"] = True
                                if not m.get("status_text") or m["status_text"].lower() == "in progress":
                                    m["status_text"] = "Match Completed"
                            else:
                                m["matchEnded"] = False
                                m["matchStarted"] = True
                        elif page_type == "upcoming":
                            m["matchEnded"] = False
                            m["matchStarted"] = False

                        if m["id"] not in parsed_ids:
                            parsed_ids.add(m["id"])
                            parsed.append(m)
                        else:
                            # Update existing match if this card has scores or better series name
                            for idx, existing in enumerate(parsed):
                                if existing.get("id") == m["id"]:
                                    comb_st = (existing.get("status_text", "") + " " + m.get("status_text", "")).lower()
                                    is_comb_abandoned = any(w in comb_st for w in ["abandon", "cancel", "no result"])
                                    is_comb_test_ongoing = not is_comb_abandoned and any(w in comb_st for w in ["stump", "trail by", "lead by", "day 1", "day 2", "day 3", "day 4", "tea", "lunch"]) and not any(w in comb_st for w in ["won by", "won the match", " beat ", "drawn", "tied", "abandon"])
                                    is_comb_toss = not is_comb_abandoned and (any(w in comb_st for w in ["opt to", "elected to", "chose to", "choose to"]) or ("toss" in comb_st and "abandon" not in comb_st))
                                    if page_type == "recent":
                                        if not is_comb_test_ongoing:
                                            existing["matchEnded"] = True
                                            existing["matchStarted"] = True
                                        else:
                                            existing["matchEnded"] = False
                                            existing["matchStarted"] = True
                                    elif page_type == "upcoming" and not existing.get("score") and not is_comb_toss and not is_comb_test_ongoing:
                                        existing["matchEnded"] = False
                                        existing["matchStarted"] = False
                                    if m.get("score") and not existing.get("score"):
                                        existing["score"] = m["score"]
                                    if m.get("series_name") and existing.get("series_name") in ["Other Matches", "Other Series"]:
                                        existing["series"] = m["series_name"]
                                        existing["series_name"] = m["series_name"]
                                    new_st = (m.get("status_text") or "").strip()
                                    old_st = (existing.get("status_text") or "").strip()
                                    is_old_rich = any(w in old_st.lower() for w in ["opt to", "elected to", "chose to", "toss", "stump", "trail", "lead", "day ", "won by", "beat", "innings break"])
                                    is_new_generic = new_st.lower() in ["match scheduled", "scheduled", "upcoming", "in progress", ""]
                                    if not (is_old_rich and is_new_generic):
                                        if new_st:
                                            existing["status_text"] = new_st
                                    if m.get("teams") and not existing.get("teams"):
                                        existing["teams"] = m["teams"]
                                    break

            # 2. Also parse upcoming schedule matches from upcoming-series (gathered as resps[3])
            try:
                sched_resp = resps[3] if len(resps) > 3 else None
                if isinstance(sched_resp, httpx.Response) and sched_resp.status_code == 200:
                    sched_soup = BeautifulSoup(sched_resp.text, "lxml")
                    sched_main = sched_soup.find("main") or sched_soup.find("div", class_="page-wrapper")
                    if sched_main:
                        for a in sched_main.find_all("a", href=True):
                            href = a.get("href", "")
                            m = re.match(r"^/live-cricket-scores/(\d+)/([\w-]+)$", href)
                            if not m:
                                continue
                            m_id = m.group(1)
                            if m_id in parsed_ids:
                                continue
                            slug = m.group(2)
                            if "-vs-" not in slug:
                                continue

                            text = a.get_text(" | ", strip=True)
                            meta = text.split("|")[0].strip() if "•" in text else ""
                            parts = meta.split("•", 1) if "•" in meta else ["Match", ""]

                            series_link = a.find_previous("a", href=lambda h: h and "/cricket-series/" in h)
                            series_name = series_link.get_text(" ", strip=True) if series_link else ""
                            series_name = " ".join(series_name.split()).strip()
                            if not series_name or series_name == "Other Series":
                                if "tour-of" in slug:
                                    s_match = re.search(r"((?:[a-z]+-)+tour-of-[a-z]+(?:-\d{4})?)", slug)
                                    if s_match:
                                        series_name = s_match.group(1).replace("-", " ").title()
                            if not series_name:
                                series_name = "Other Matches"

                            raw_title = (a.get("title") or slug.replace("-", " ").title()).strip()
                            status_from_title = None
                            if " - " in raw_title:
                                t_parts = raw_title.split(" - ", 1)
                                match_name = t_parts[0].strip()
                                status_from_title = t_parts[1].strip()
                            else:
                                match_name = raw_title

                            slug_parts = slug.split("-")
                            vs_idx = slug_parts.index("vs") if "vs" in slug_parts else -1
                            teams = []
                            if vs_idx > 0 and vs_idx + 1 < len(slug_parts):
                                teams = [slug_parts[vs_idx - 1].upper(), slug_parts[vs_idx + 1].upper()]
                            elif " vs " in match_name:
                                t_parts = match_name.split(" vs ", 1)
                                teams = [t_parts[0].strip(), t_parts[1].split(",")[0].strip()]

                            status_text = status_from_title if status_from_title else "Scheduled"
                            is_in_progress = any(w in status_text.lower() for w in ["opt to", "elected to", "live", "session", "day "])

                            parsed.append({
                                "id": m_id,
                                "name": match_name,
                                "matchType": parts[0].strip() if parts[0] else "Match",
                                "venue": parts[1].strip() if len(parts) > 1 else "",
                                "status_text": status_text,
                                "series": series_name,
                                "series_name": series_name,
                                "teams": teams,
                                "score": None,
                                "matchStarted": is_in_progress,
                                "href": href
                            })
                            parsed_ids.add(m_id)
            except Exception as sched_err:
                print("Error parsing upcoming schedule:", sched_err)

            def priority_key(m):
                series = (m.get("series") or "").lower()
                name = (m.get("name") or "").lower()
                teams = [t.lower() for t in m.get("teams", [])]
                if any("ind" in t or "india" in t for t in teams) or "india" in name:
                    return 0
                top_teams = ["australia", "england", "new zealand", "south africa", "pakistan", "sri lanka", "west indies", "afghanistan", "bangladesh"]
                if any(any(tt in t for tt in top_teams) for t in teams):
                    return 1
                if "tour of" in series or "icc" in series or "world cup" in series:
                    return 2
                return 3

            parsed.sort(key=priority_key)
            set_cached("all_matches", parsed)
            return parsed
        except Exception as e:
            print("Error fetching all matches:", e)
            return []

    @classmethod
    async def fetch_news(cls) -> List[Dict[str, Any]]:
        cached = get_cached("cricket_news", ttl=60)
        if cached:
            return cached

        try:
            url = f"https://www.cricbuzz.com/cricket-news?_={time.time_ns()}"
            async with httpx.AsyncClient(timeout=10.0, follow_redirects=True) as client:
                response = await client.get(url, headers=cls.HEADERS)
                response.raise_for_status()

            soup = BeautifulSoup(response.text, "lxml")
            items = []
            seen_titles = set()

            for a in soup.find_all("a", href=lambda h: h and "/cricket-news/" in h):
                img = a.find("img")
                if img and img.get("src"):
                    title = a.get("title") or img.get("title") or a.get_text(" ", strip=True)
                    title = " ".join(title.split()).strip()
                    if not title or title in seen_titles:
                        continue
                    seen_titles.add(title)

                    img_src = img.get("src")
                    if "?d=" in img_src:
                        img_src = re.sub(r"d=\w+", "d=high", img_src)

                    p = a.find_next_sibling("div") or a.find_next("div")
                    desc = p.get_text(" ", strip=True) if p else ""
                    desc = " ".join(desc.split()).strip()

                    items.append({
                        "title": title,
                        "image": img_src,
                        "description": desc,
                        "href": a.get("href"),
                        "source": "Cricbuzz Editorial"
                    })

            set_cached("cricket_news", items)
            return items
        except Exception as e:
            print("Error fetching cricket news:", e)
            return []

    @classmethod
    async def fetch_scorecard(cls, match_id: str) -> Dict[str, Any]:
        cached = get_cached(f"scorecard_{match_id}", ttl=5)
        if cached:
            return cached

        try:
            url = f"https://www.cricbuzz.com/live-cricket-scorecard/{match_id}?_={time.time_ns()}"
            async with httpx.AsyncClient(timeout=10.0, follow_redirects=True) as client:
                response = await client.get(url, headers=cls.HEADERS)
                response.raise_for_status()

            text = response.text
            formatted_innings = []

            # Check for Next.js App Router RSC scorecardApiData
            marker = "scorecardApiData"
            pos = text.find(marker)
            if pos != -1:
                sc_arr_start = text.find("[", pos)
                if sc_arr_start != -1:
                    bracket_depth = 0
                    sc_arr_end = -1
                    for i in range(sc_arr_start, len(text)):
                        if text[i] == "[":
                            bracket_depth += 1
                        elif text[i] == "]":
                            bracket_depth -= 1
                            if bracket_depth == 0:
                                sc_arr_end = i
                                break

                    if sc_arr_end != -1:
                        raw_arr = text[sc_arr_start:sc_arr_end + 1]
                        cleaned_json = raw_arr.replace('\\\\\\"', '"').replace('\\"', '"').replace('\\\\\\\\', '\\')
                        try:
                            sc_list = json.loads(cleaned_json)
                            for inn in sc_list:
                                bat_team = inn.get("batTeamDetails", {})
                                bowl_team = inn.get("bowlTeamDetails", {})
                                score_details = inn.get("scoreDetails", {})
                                extras = inn.get("extrasData", {})

                                batting = []
                                did_not_bat = []
                                for b in bat_team.get("batsmenData", {}).values():
                                    name = b.get("batName")
                                    out_desc = b.get("outDesc", "").strip()
                                    runs = b.get("runs", 0)
                                    balls = b.get("balls", 0)
                                    dots = b.get("dots", 0)

                                    b_id = b.get("batId")
                                    if balls == 0 and not out_desc and runs == 0 and dots == 0:
                                        did_not_bat.append({
                                            "id": str(b_id) if b_id else None,
                                            "name": name
                                        })
                                    else:
                                        batting.append({
                                            "id": str(b_id) if b_id else None,
                                            "name": name,
                                            "runs": runs,
                                            "balls": balls,
                                            "fours": b.get("fours", 0),
                                            "sixes": b.get("sixes", 0),
                                            "strikeRate": b.get("strikeRate", 0.0),
                                            "out": out_desc if out_desc else "not out"
                                        })

                                bowling = []
                                for bw in bowl_team.get("bowlersData", {}).values():
                                    bw_id = bw.get("bowlerId")
                                    bowling.append({
                                        "id": str(bw_id) if bw_id else None,
                                        "bowler": bw.get("bowlName"),
                                        "name": bw.get("bowlName"),
                                        "overs": str(bw.get("overs", "0")),
                                        "maidens": bw.get("maidens", 0),
                                        "runs": bw.get("runs", 0),
                                        "wickets": bw.get("wickets", 0),
                                        "economy": float(bw.get("economy", 0.0))
                                    })

                                fow = []
                                for w in inn.get("wicketsData", {}).values():
                                    fow.append({
                                        "wkt_nbr": w.get("wktNbr"),
                                        "score": w.get("wktRuns"),
                                        "over": w.get("wktOver"),
                                        "batsman": w.get("batName")
                                    })
                                fow.sort(key=lambda x: x.get("wkt_nbr", 0))

                                formatted_innings.append({
                                    "id": inn.get("inningsId"),
                                    "team": bat_team.get("batTeamName"),
                                    "short_name": bat_team.get("batTeamShortName"),
                                    "runs": score_details.get("runs", 0),
                                    "wickets": score_details.get("wickets", 0),
                                    "overs": str(score_details.get("overs", "0")),
                                    "run_rate": score_details.get("runRate", 0.0),
                                    "score": f"{score_details.get('runs', 0)}/{score_details.get('wickets', 0)}",
                                    "batting": batting,
                                    "did_not_bat": did_not_bat,
                                    "extras": extras,
                                    "fall_of_wickets": fow,
                                    "bowling": bowling
                                })
                        except Exception as err:
                            print("Scorecard RSC JSON parse error:", err)

            # Fallback current batting & bowling from commentary page if innings was empty
            current_batting = []
            current_bowling = []
            if not formatted_innings:
                soup = BeautifulSoup(text, "lxml")
                for el in soup.find_all(string=lambda t: t and t.strip() == "Batter"):
                    cont = el.parent
                    for _ in range(4):
                        if cont.parent:
                            cont = cont.parent

                    parts = cont.get_text(" | ", strip=True).split(" | ")
                    try:
                        b_idx = parts.index("Batter")
                        bw_idx = parts.index("Bowler") if "Bowler" in parts else len(parts)

                        batter_tokens = [p for p in parts[b_idx + 6:bw_idx] if p not in ["*", "View match performance", "View profile"]]
                        i = 0
                        while i < len(batter_tokens):
                            if i + 5 < len(batter_tokens):
                                name = batter_tokens[i]
                                r = batter_tokens[i + 1]
                                b = batter_tokens[i + 2]
                                fours = batter_tokens[i + 3]
                                sixes = batter_tokens[i + 4]
                                sr = batter_tokens[i + 5]
                                if r.isdigit() and b.isdigit():
                                    current_batting.append({
                                        "name": name,
                                        "runs": int(r),
                                        "balls": int(b),
                                        "fours": int(fours) if fours.isdigit() else 0,
                                        "sixes": int(sixes) if sixes.isdigit() else 0,
                                        "strikeRate": float(sr) if sr.replace(".", "", 1).isdigit() else 0.0,
                                        "out": "not out"
                                    })
                                    i += 6
                                    continue
                            i += 1

                        if "Bowler" in parts:
                            bowler_tokens = [p for p in parts[bw_idx + 6:] if p not in ["*", "View match performance", "View profile"]]
                            j = 0
                            while j < len(bowler_tokens):
                                if j + 5 < len(bowler_tokens):
                                    b_name = bowler_tokens[j]
                                    o = bowler_tokens[j + 1]
                                    m = bowler_tokens[j + 2]
                                    r = bowler_tokens[j + 3]
                                    w = bowler_tokens[j + 4]
                                    eco = bowler_tokens[j + 5]
                                    if o.replace(".", "", 1).isdigit() and r.isdigit() and w.isdigit():
                                        current_bowling.append({
                                            "bowler": b_name,
                                            "name": b_name,
                                            "overs": o,
                                            "maidens": int(m) if m.isdigit() else 0,
                                            "runs": int(r),
                                            "wickets": int(w),
                                            "economy": float(eco) if eco.replace(".", "", 1).isdigit() else 0.0
                                        })
                                        j += 6
                                        continue
                                j += 1
                    except Exception as e:
                        print("Scorecard parse fallback error:", e)
                    break
            # Top active batting & bowling from latest innings
            if formatted_innings:
                latest_inn = formatted_innings[-1]
                current_batting = [b for b in latest_inn["batting"] if b["out"] in ["not out", "batting"]]
                current_bowling = latest_inn["bowling"]

            pom = cls.extract_player_of_the_match(text)
            if not pom:
                try:
                    score_info = await cls.fetch_score(match_id)
                    pom = score_info.player_of_the_match
                except Exception:
                    pom = None

            result = {
                "status": "success",
                "data": {
                    "innings": formatted_innings,
                    "batting": current_batting,
                    "bowling": current_bowling,
                    "player_of_the_match": pom
                }
            }
            set_cached(f"scorecard_{match_id}", result)
            return result
        except Exception as e:
            return {
                "status": "error",
                "message": str(e),
                "data": {
                    "innings": [],
                    "batting": [],
                    "bowling": []
                }
            }

    @classmethod
    async def fetch_player_profile(cls, player_id: str, name: str = "") -> Dict[str, Any]:
        cached = get_cached(f"player_{player_id}", ttl=3600)
        if cached:
            return cached

        try:
            slug = re.sub(r"[^a-zA-Z0-9]+", "-", name.lower()).strip("-") if name else ""
            url = f"https://www.cricbuzz.com/profiles/{player_id}/{slug}" if slug else f"https://www.cricbuzz.com/profiles/{player_id}"

            async with httpx.AsyncClient(timeout=15.0, follow_redirects=True) as client:
                response = await client.get(url, headers=cls.HEADERS)
                if response.status_code != 200:
                    return {"status": "error", "message": f"Player not found (HTTP {response.status_code})"}

            soup = BeautifulSoup(response.text, "lxml")
            h1 = soup.find("h1")
            player_name = h1.get_text(strip=True) if h1 else name

            img_src = ""
            for im in soup.find_all("img"):
                src = im.get("src", "")
                if "cricbuzz.com/a/img" in src and ("profile" in src or "thumb" in src or (slug and slug in src)):
                    img_src = src
                    break

            personal = {}
            for label in ["Born", "Birth Place", "Height", "Role", "Batting Style", "Bowling Style", "ICC Rankings"]:
                el = soup.find(string=lambda t: t and t.strip() == label)
                if el:
                    p = el.parent
                    next_val = p.find_next_sibling() or p.parent.find_next_sibling()
                    if next_val:
                        personal[label.lower().replace(" ", "_")] = next_val.get_text(strip=True)

            tables = soup.find_all("table")
            batting_career = {}
            bowling_career = {}

            if len(tables) > 1:
                t_bat = tables[1]
                rows = t_bat.find_all("tr")
                if rows:
                    headers_row = [th.get_text(strip=True) for th in rows[0].find_all(["th", "td"]) if th.get_text(strip=True)]
                    stats = []
                    for tr in rows[1:]:
                        cells = [td.get_text(strip=True) for td in tr.find_all(["th", "td"])]
                        if cells:
                            stats.append({"label": cells[0], "values": cells[1:]})
                    batting_career = {"formats": headers_row, "stats": stats}

            if len(tables) > 2:
                t_bowl = tables[2]
                rows = t_bowl.find_all("tr")
                if rows:
                    headers_row = [th.get_text(strip=True) for th in rows[0].find_all(["th", "td"]) if th.get_text(strip=True)]
                    stats = []
                    for tr in rows[1:]:
                        cells = [td.get_text(strip=True) for td in tr.find_all(["th", "td"])]
                        if cells:
                            stats.append({"label": cells[0], "values": cells[1:]})
                    bowling_career = {"formats": headers_row, "stats": stats}

            res = {
                "status": "success",
                "data": {
                    "id": player_id,
                    "name": player_name,
                    "image": img_src,
                    "personal_info": personal,
                    "batting_career": batting_career,
                    "bowling_career": bowling_career
                }
            }
            set_cached(f"player_{player_id}", res)
            return res
        except Exception as e:
            return {"status": "error", "message": str(e)}

    @classmethod
    async def fetch_commentary(cls, match_id: str) -> Dict[str, Any]:
        cached = get_cached(f"commentary_{match_id}", ttl=5)
        if cached:
            return cached

        try:
            url = f"https://www.cricbuzz.com/live-cricket-scores/{match_id}?_={time.time_ns()}"
            async with httpx.AsyncClient(timeout=10.0, follow_redirects=True) as client:
                response = await client.get(url, headers=cls.HEADERS)
                response.raise_for_status()

            text = response.text
            marker = "commentaryPageData"
            idx = text.find(marker)
            if idx == -1:
                return {"status": "success", "matchId": match_id, "miniscore": None, "data": []}

            brace_start = text.find("{", idx)
            if brace_start == -1:
                return {"status": "success", "matchId": match_id, "miniscore": None, "data": []}

            depth = 0
            brace_end = -1
            text_len = len(text)
            for i in range(brace_start, text_len):
                if text[i] == "{":
                    depth += 1
                elif text[i] == "}":
                    depth -= 1
                    if depth == 0:
                        brace_end = i
                        break

            if brace_end == -1:
                return {"status": "success", "matchId": match_id, "miniscore": None, "data": []}

            raw_json = text[brace_start:brace_end+1].replace('\\\\\\"', '"').replace('\\"', '"').replace('\\\\\\\\', '\\')
            data = json.loads(raw_json)

            match_comm = data.get("matchCommentary", {})
            miniscore = data.get("miniscore", {})
            minis_inn = int(miniscore.get("inningsId") or 1) if str(miniscore.get("inningsId", "")).isdigit() else 1

            formatted = []
            for comm_id, item in match_comm.items():
                ctype = item.get("commType", "commentary")
                timestamp = item.get("timestamp") or 0
                item_inn = item.get("inningsId")
                innings_id = int(item_inn) if item_inn and str(item_inn).isdigit() else minis_inn
                events = item.get("event") or []
                if isinstance(events, str):
                    events = [events]

                # Skip duplicate flash alert lines
                raw_text = item.get("commText", "")
                if "THATS OUT!!" in raw_text:
                    continue

                if ctype == "over-separator":
                    over_sep = item.get("overSeparator") or {}
                    over_num = over_sep.get("overNumber")
                    over_runs = over_sep.get("overRuns", 0)
                    summary = (over_sep.get("overSummary") or "").strip()
                    bat_team = over_sep.get("batTeamObj", {}).get("teamName", "")
                    bat_score = over_sep.get("batTeamObj", {}).get("teamScore", "")
                    striker = over_sep.get("batStrikerObj", {})
                    non_striker = over_sep.get("batNonStrikerObj", {})
                    bowler = over_sep.get("bowlerObj", {})

                    sub_title = f"End of Over {over_num}"
                    if bat_team and bat_score:
                        sub_title += f" • {bat_team} {bat_score}"

                    desc_parts = []
                    if summary:
                        desc_parts.append(f"Over summary: {summary} ({over_runs} runs)")
                    else:
                        desc_parts.append(f"{over_runs} runs in over {over_num}")

                    if bowler and bowler.get("playerName"):
                        desc_parts.append(f"Bowler: {bowler.get('playerName')} ({bowler.get('playerScore', '')})")
                    if striker and striker.get("playerName"):
                        desc_parts.append(f"Batting: {striker.get('playerName')} {striker.get('playerScore', '')}")

                    formatted.append({
                        "id": f"over-{over_num}-{comm_id}",
                        "type": "over-separator",
                        "inningsId": innings_id,
                        "inning": innings_id,
                        "batTeam": bat_team,
                        "over": f"Over {over_num}",
                        "overNumber": over_num,
                        "badge": "OV",
                        "badgeType": "expert",
                        "title": sub_title,
                        "desc": " | ".join(desc_parts),
                        "overSummary": summary,
                        "overRuns": over_runs,
                        "timestamp": timestamp,
                        "time": "Over Break",
                        "tag": f"Over {over_num}"
                    })
                else:
                    ball = item.get("ballMetric")
                    clean_text = re.sub(r"<[^>]+>", "", raw_text).strip()

                    # Determine if this item is a genuine ball delivery
                    is_ball = False
                    ball_float = None
                    if ball is not None and str(ball) != "$undefined":
                        try:
                            ball_float = float(ball)
                            is_ball = True
                        except ValueError:
                            is_ball = False

                    if is_ball:
                        over_str = str(ball)
                        text_lower = clean_text.lower()
                        badge = "0"
                        badge_type = "dot"
                        tag = None

                        if "four" in events or "<b>four</b>" in raw_text.lower() or ", four" in text_lower or text_lower.endswith(", four"):
                            badge = "4"
                            badge_type = "four"
                            tag = "Boundary 4"
                        elif "six" in events or "<b>six</b>" in raw_text.lower() or ", six" in text_lower or text_lower.endswith(", six"):
                            badge = "6"
                            badge_type = "six"
                            tag = "Maximum 6"
                        elif "wicket" in events or "<b>out</b>" in raw_text.lower() or ", out " in clean_text.lower() or re.search(r",\s*out\s+(bowled|caught|lbw|run out|stumped)", text_lower):
                            badge = "W"
                            badge_type = "wicket"
                            tag = "Wicket ☝️"
                        elif "wide" in events or "wide" in text_lower or "wd" in text_lower:
                            badge = "Wd"
                            badge_type = "extra"
                            tag = "Wide"
                        elif "no-ball" in events or "no ball" in text_lower or "nb" in text_lower:
                            badge = "Nb"
                            badge_type = "extra"
                            tag = "No Ball"
                        elif "1 run" in text_lower or "one" in events:
                            badge = "1"
                            badge_type = "run"
                        elif "2 runs" in text_lower or "two" in events:
                            badge = "2"
                            badge_type = "run"
                        elif "3 runs" in text_lower or "three" in events:
                            badge = "3"
                            badge_type = "run"
                        elif "leg bye" in text_lower or "bye" in text_lower:
                            badge = "B"
                            badge_type = "run"

                        batsman = item.get("batsmanDetails", {}).get("playerName") if isinstance(item.get("batsmanDetails"), dict) else None
                        bowler = item.get("bowlerDetails", {}).get("playerName") if isinstance(item.get("bowlerDetails"), dict) else None

                        parts = clean_text.split(",", 2)
                        if len(parts) >= 2 and ("to" in parts[0] or badge_type in ["four", "six", "wicket"]):
                            title = f"{parts[0].strip()}, {parts[1].strip()}"
                            desc = parts[2].strip() if len(parts) > 2 else ""
                        else:
                            title = clean_text
                            desc = ""

                        formatted.append({
                            "id": f"ball-{comm_id}",
                            "type": "ball",
                            "inningsId": innings_id,
                            "inning": innings_id,
                            "over": over_str,
                            "ballMetric": ball_float,
                            "badge": badge,
                            "badgeType": badge_type,
                            "title": title,
                            "desc": desc or clean_text,
                            "rawText": clean_text,
                            "batsman": batsman,
                            "bowler": bowler,
                            "tag": tag,
                            "timestamp": timestamp,
                            "time": f"Ball {over_str}"
                        })
                    else:
                        # Non-ball item: Post-match quote, Match wrap-up, or Tactical note
                        match_quote = re.match(r"^([A-Za-z\s.\-]+):\s*(.+)$", clean_text, re.DOTALL)
                        if match_quote:
                            speaker = match_quote.group(1).strip()
                            speech = match_quote.group(2).strip()
                            formatted.append({
                                "id": f"speech-{comm_id}",
                                "type": "interview",
                                "inningsId": innings_id,
                                "inning": innings_id,
                                "over": "QUOTE",
                                "badge": "🎙️",
                                "badgeType": "expert",
                                "title": f"{speaker} (Post-Match Take)",
                                "desc": speech,
                                "tag": "Player Quote 🎙️",
                                "timestamp": timestamp,
                                "time": "Presentation"
                            })
                        elif any(k in clean_text.lower() for k in ["clinch", "win", "local time", "semis", "champions", "victory"]):
                            formatted.append({
                                "id": f"wrap-{comm_id}",
                                "type": "match_wrap",
                                "inningsId": innings_id,
                                "inning": innings_id,
                                "over": "FINAL",
                                "badge": "🏆",
                                "badgeType": "expert",
                                "title": "Match Summary & Wrap-up",
                                "desc": clean_text,
                                "tag": "Match Result 🏆",
                                "timestamp": timestamp,
                                "time": "Wrap-up"
                            })
                        else:
                            formatted.append({
                                "id": f"note-{comm_id}",
                                "type": "note",
                                "inningsId": innings_id,
                                "inning": innings_id,
                                "over": "NOTE",
                                "badge": "📋",
                                "badgeType": "expert",
                                "title": "Match Note",
                                "desc": clean_text,
                                "tag": "Tactical Note",
                                "timestamp": timestamp,
                                "time": "Update"
                            })

            formatted.sort(key=lambda x: x.get("timestamp", 0), reverse=True)

            res = {
                "status": "success",
                "matchId": match_id,
                "miniscore": miniscore,
                "data": formatted
            }
            set_cached(f"commentary_{match_id}", res)
            return res
        except Exception as e:
            return {"status": "error", "message": str(e), "data": []}


@app.get("/news")
async def get_news():
    news = await ScoreService.fetch_news()
    return {
        "status": "success",
        "data": news
    }


@app.get("/matches")
async def get_matches():
    matches = await ScoreService.fetch_all_matches()
    return {
        "status": "success",
        "data": matches
    }


@app.get("/match/{match_id}")
async def get_match_detail(match_id: str):
    try:
        MatchValidator(score=match_id)
    except Exception:
        return JSONResponse(status_code=422, content={"status": "error", "message": "invalid match id"})

    score_info = await ScoreService.fetch_score(match_id)
    all_matches = await ScoreService.fetch_all_matches()
    match_meta = next((m for m in all_matches if m["id"] == match_id), None)

    has_live_score = score_info.score != NOT_FOUND
    has_batsmen = any(b.name != NOT_FOUND for b in score_info.current_batsmen)
    match_started = bool(match_meta and (match_meta.get("score") or match_meta.get("matchStarted"))) or has_live_score or has_batsmen

    status_text = match_meta["status_text"] if match_meta else ""
    if getattr(score_info, "status_text", None):
        status_text = score_info.status_text
    elif (has_live_score or has_batsmen) and (not status_text or status_text.lower() in ["scheduled", "match scheduled", "upcoming"]):
        status_text = f"Live: {score_info.score}" if has_live_score else "In Progress"

    series_name = (match_meta.get("series_name") or match_meta.get("series")) if match_meta else "Other Matches"
    teams = match_meta.get("teams", []) if match_meta else []

    return {
        "status": "success",
        "data": {
            "id": match_id,
            "name": match_meta["name"] if match_meta else score_info.title,
            "title": score_info.title,
            "series": series_name,
            "series_name": series_name,
            "matchType": match_meta["matchType"] if match_meta else "Match",
            "venue": match_meta["venue"] if match_meta else "",
            "status_text": status_text,
            "matchStarted": match_started,
            "teams": teams,
            "score": match_meta["score"] if match_meta else None,
            "live_score_display": score_info.score,
            "current_batsmen": [b.model_dump() for b in score_info.current_batsmen],
            "current_bowler": score_info.current_bowler.model_dump(),
            "player_of_the_match": score_info.player_of_the_match
        }
    }


@app.get("/scorecard/{match_id}")
async def get_scorecard(match_id: str):
    try:
        MatchValidator(score=match_id)
    except Exception:
        return JSONResponse(status_code=422, content={"status": "error", "message": "invalid match id"})

    return await ScoreService.fetch_scorecard(match_id)


@app.get("/commentary/{match_id}")
async def get_commentary(match_id: str):
    try:
        MatchValidator(score=match_id)
    except Exception:
        return JSONResponse(status_code=422, content={"status": "error", "message": "invalid match id"})

    return await ScoreService.fetch_commentary(match_id)


@app.get("/player/{player_id}")
async def get_player(player_id: str, name: str = Query("")):
    return await ScoreService.fetch_player_profile(player_id, name)


@app.get("/docs", include_in_schema=False)
async def custom_swagger_docs():
    html = get_swagger_ui_html(
        openapi_url=app.openapi_url,
        title="Live Cricket Score API Docs",
        swagger_favicon_url="https://fastapi.tiangolo.com/img/favicon.png"
    )
    return HTMLResponse(content=html.body.decode("utf-8"))


@app.get("/", response_model=ScoreResponse)
async def root(
    score: Optional[str] = Query(None, min_length=4, max_length=20),
    text: bool = Query(False)
):
    if score is None:
        return ScoreResponse(
            status="success",
            title="Live Score API",
            score=NOT_FOUND,
            current_batsmen=ScoreService.default_batsmen(),
            current_bowler=Bowler()
        )

    try:
        MatchValidator(score=score)
    except Exception:
        return JSONResponse(
            status_code=422,
            content={"status": "error", "code": 422, "message": "score id must be at least 4 digits"}
        )

    result = await ScoreService.fetch_score(score)
    if text:
        return PlainTextResponse(ScoreService.format_tree(result))
    return result


@app.exception_handler(APIError)
async def api_error_handler(request: Request, exc: APIError):
    return JSONResponse(
        status_code=exc.status_code,
        content={"status": "error", "code": exc.status_code, "message": exc.message}
    )


@app.exception_handler(StarletteHTTPException)
async def http_error_handler(request: Request, exc: StarletteHTTPException):
    return JSONResponse(
        status_code=exc.status_code,
        content={"status": "error", "code": exc.status_code, "message": "invalid api route"}
    )
