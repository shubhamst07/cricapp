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

            res = ScoreResponse(
                status="success",
                title=title,
                score=score,
                current_batsmen=batsmen,
                current_bowler=Bowler(name=bowler_name)
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
    def parse_cards_from_html(cls, html_text: str) -> List[Dict[str, Any]]:
        soup = BeautifulSoup(html_text, "lxml")
        cards = soup.find_all("a", class_=lambda c: c and "bg-cbWhite" in c)
        parsed = []

        for card in cards:
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

            children = [c for c in card.children if hasattr(c, "get_text")]
            if len(children) < 2:
                continue

            meta_info = children[0].get_text(" ", strip=True)
            status_text = children[2].get_text(" ", strip=True) if len(children) > 2 else ""

            teams = []
            scores = []
            team_rows = children[1].find_all("div", class_=lambda c: c and "justify-between" in c)

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
                                wickets = int(sm.group(2)) if sm.group(2) else 0
                                overs = sm.group(3) if (sm.group(3) and sm.group(3) != "0" and sm.group(3) != "0.0") else None
                                scores.append({
                                    "inning": f"{team_name} Inn {s_idx + 1}" if len(sub_parts) > 1 else f"{team_name} Inning 1",
                                    "batting_team": team_name,
                                    "runs": runs,
                                    "r": runs,
                                    "wickets": wickets,
                                    "w": wickets,
                                    "overs": overs,
                                    "o": overs,
                                    "display_score": f"{runs}/{wickets}" if wickets else str(runs)
                                })
                    else:
                        sm = re.search(r"(\d+)(?:[-/](\d+))?\s*(?:\(([\d.]+)\s*(?:ov)?\))?", score_str)
                        if sm:
                            runs = int(sm.group(1))
                            wickets = int(sm.group(2)) if sm.group(2) else 0
                            overs = sm.group(3) if (sm.group(3) and sm.group(3) != "0" and sm.group(3) != "0.0") else None
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

            # Normalize status_text for upcoming matches with no live score
            if not scores:
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
                "href": href
            })

        return parsed

    @classmethod
    async def fetch_all_matches(cls) -> List[Dict[str, Any]]:
        cached = get_cached("all_matches", ttl=10)
        if cached:
            return cached

        try:
            url_live = f"https://www.cricbuzz.com/cricket-match/live-scores?_={time.time_ns()}"
            url_recent = f"https://www.cricbuzz.com/cricket-match/live-scores/recent-matches?_={time.time_ns()}"
            url_upcoming = f"https://www.cricbuzz.com/cricket-match/live-scores/upcoming-matches?_={time.time_ns()}"

            async with httpx.AsyncClient(timeout=10.0, follow_redirects=True) as client:
                resps = await asyncio.gather(
                    client.get(url_live, headers=cls.HEADERS),
                    client.get(url_recent, headers=cls.HEADERS),
                    client.get(url_upcoming, headers=cls.HEADERS),
                    return_exceptions=True
                )

            parsed = []
            parsed_ids = set()

            for resp in resps:
                if isinstance(resp, httpx.Response) and resp.status_code == 200:
                    matches = cls.parse_cards_from_html(resp.text)
                    for m in matches:
                        if m["id"] not in parsed_ids:
                            parsed_ids.add(m["id"])
                            parsed.append(m)

            # 2. Also fetch upcoming schedule matches from upcoming-series
            try:
                sched_url = f"https://www.cricbuzz.com/cricket-schedule/upcoming-series/international?_={time.time_ns()}"
                async with httpx.AsyncClient(timeout=10.0, follow_redirects=True) as client:
                    sched_resp = await client.get(sched_url, headers=cls.HEADERS)
                    if sched_resp.status_code == 200:
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
                                series_name = series_link.get_text(" ", strip=True) if series_link else "Other Series"
                                series_name = " ".join(series_name.split()).strip()
                                if not series_name:
                                    series_name = "Other Series"

                                slug_parts = slug.split("-")
                                vs_idx = slug_parts.index("vs") if "vs" in slug_parts else -1
                                teams = []
                                if vs_idx > 0 and vs_idx + 1 < len(slug_parts):
                                    teams = [slug_parts[vs_idx - 1].upper(), slug_parts[vs_idx + 1].upper()]

                                title = a.get("title") or slug.replace("-", " ").title()

                                parsed.append({
                                    "id": m_id,
                                    "name": title.strip(),
                                    "matchType": parts[0].strip() if parts[0] else "Match",
                                    "venue": parts[1].strip() if len(parts) > 1 else "",
                                    "status_text": "Scheduled",
                                    "series": series_name,
                                    "series_name": series_name,
                                    "teams": teams,
                                    "score": None,
                                    "href": href
                                })
                                parsed_ids.add(m_id)
            except Exception as sched_err:
                print("Error fetching upcoming schedule:", sched_err)

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
            else:
                # Top active batting & bowling from latest innings
                latest_inn = formatted_innings[-1]
                current_batting = [b for b in latest_inn["batting"] if b["out"] in ["not out", "batting"]]
                current_bowling = latest_inn["bowling"]

            result = {
                "status": "success",
                "data": {
                    "innings": formatted_innings,
                    "batting": current_batting,
                    "bowling": current_bowling
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

    return {
        "status": "success",
        "data": {
            "id": match_id,
            "name": match_meta["name"] if match_meta else score_info.title,
            "title": score_info.title,
            "matchType": match_meta["matchType"] if match_meta else "Match",
            "venue": match_meta["venue"] if match_meta else "",
            "status_text": match_meta["status_text"] if match_meta else "",
            "teams": match_meta["teams"] if match_meta else [],
            "score": match_meta["score"] if match_meta else None,
            "live_score_display": score_info.score,
            "current_batsmen": [b.model_dump() for b in score_info.current_batsmen],
            "current_bowler": score_info.current_bowler.model_dump()
        }
    }


@app.get("/scorecard/{match_id}")
async def get_scorecard(match_id: str):
    try:
        MatchValidator(score=match_id)
    except Exception:
        return JSONResponse(status_code=422, content={"status": "error", "message": "invalid match id"})

    return await ScoreService.fetch_scorecard(match_id)


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
