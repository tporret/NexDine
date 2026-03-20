import base64
import json
import os
from datetime import datetime, timedelta
from typing import Any, Dict, Optional

import google.auth
import requests
from fastapi import FastAPI, HTTPException
from googleapiclient.discovery import build

app = FastAPI(title="NexDine Vapi Traffic Controller")

CALENDAR_SCOPE = ["https://www.googleapis.com/auth/calendar"]
DEFAULT_TIMEZONE = os.getenv("RESERVATION_TIMEZONE", "UTC")
DEFAULT_CALENDAR_ID = os.getenv("CALENDAR_ID", "")
WP_SITE_URL = os.getenv("WP_SITE_URL", "")
WP_REST_RESERVATION_ENDPOINT = os.getenv(
    "WP_REST_RESERVATION_ENDPOINT", "/wp-json/wp/v2/vapi_reservation"
)
WP_API_USER = os.getenv("WP_API_USER", "")
WP_APP_PASSWORD = os.getenv("WP_APP_PASSWORD", "")
DEFAULT_RESERVATION_DURATION_MINUTES = int(
    os.getenv("RESERVATION_DURATION_MINUTES", "90")
)


def _parse_tool_arguments(raw_args: Any) -> Dict[str, Any]:
    if isinstance(raw_args, dict):
        return raw_args

    if isinstance(raw_args, str):
        try:
            decoded = json.loads(raw_args)
            return decoded if isinstance(decoded, dict) else {}
        except json.JSONDecodeError:
            return {}

    return {}


def _extract_tool_call(payload: Dict[str, Any]) -> Dict[str, Any]:
    message = payload.get("message") or {}
    tool_calls = message.get("toolCalls") or []

    if not isinstance(tool_calls, list) or not tool_calls:
        raise HTTPException(status_code=400, detail="No toolCalls found in payload")

    tool_call = tool_calls[0]
    if not isinstance(tool_call, dict):
        raise HTTPException(status_code=400, detail="Invalid toolCall format")

    return tool_call


def _parse_date_time(date_str: str, time_str: str) -> datetime:
    date_str = (date_str or "").strip()
    time_str = (time_str or "").strip()

    if not date_str:
        raise HTTPException(status_code=400, detail="Missing required slot: date")

    if not time_str:
        raise HTTPException(status_code=400, detail="Missing required slot: time")

    parsed_date = None
    for fmt in ("%Y-%m-%d", "%m/%d/%Y", "%d-%m-%Y"):
        try:
            parsed_date = datetime.strptime(date_str, fmt).date()
            break
        except ValueError:
            continue

    if parsed_date is None:
        try:
            parsed_date = datetime.fromisoformat(date_str).date()
        except ValueError as exc:
            raise HTTPException(status_code=400, detail=f"Unsupported date format: {date_str}") from exc

    parsed_time = None
    for fmt in ("%H:%M", "%I:%M %p", "%I %p"):
        try:
            parsed_time = datetime.strptime(time_str, fmt).time()
            break
        except ValueError:
            continue

    if parsed_time is None:
        try:
            parsed_time = datetime.fromisoformat(f"2000-01-01T{time_str}").time()
        except ValueError as exc:
            raise HTTPException(status_code=400, detail=f"Unsupported time format: {time_str}") from exc

    return datetime.combine(parsed_date, parsed_time)


def _calendar_service():
    credentials, _ = google.auth.default(scopes=CALENDAR_SCOPE)
    return build("calendar", "v3", credentials=credentials, cache_discovery=False)


def _create_calendar_event(
    calendar_id: str,
    slots: Dict[str, Any],
    start_dt: datetime,
    end_dt: datetime,
) -> Dict[str, Any]:
    service = _calendar_service()
    summary = f"Reservation: {slots.get('customer_name', 'Guest')} ({slots.get('party_size', 'N/A')})"

    description = (
        f"Customer: {slots.get('customer_name', 'N/A')}\n"
        f"Phone: {slots.get('phone', 'N/A')}\n"
        f"Party Size: {slots.get('party_size', 'N/A')}\n"
        f"Seating Preference: {slots.get('seating_preference', 'N/A')}\n"
        f"Occasion: {slots.get('occasion', 'N/A')}\n"
        f"Dietary Notes: {slots.get('notes', 'N/A')}"
    )

    event = {
        "summary": summary,
        "description": description,
        "start": {
            "dateTime": start_dt.isoformat(),
            "timeZone": DEFAULT_TIMEZONE,
        },
        "end": {
            "dateTime": end_dt.isoformat(),
            "timeZone": DEFAULT_TIMEZONE,
        },
    }

    return (
        service.events()
        .insert(calendarId=calendar_id, body=event)
        .execute()
    )


def _wp_auth_header() -> Dict[str, str]:
    if not WP_API_USER or not WP_APP_PASSWORD:
        raise HTTPException(
            status_code=500,
            detail="Missing WP_API_USER or WP_APP_PASSWORD environment variables",
        )

    token = base64.b64encode(f"{WP_API_USER}:{WP_APP_PASSWORD}".encode("utf-8")).decode("utf-8")
    return {
        "Authorization": f"Basic {token}",
        "Content-Type": "application/json",
    }


def _sync_wordpress_reservation(slots: Dict[str, Any]) -> Dict[str, Any]:
    if not WP_SITE_URL:
        raise HTTPException(status_code=500, detail="Missing WP_SITE_URL environment variable")

    endpoint = f"{WP_SITE_URL.rstrip('/')}{WP_REST_RESERVATION_ENDPOINT}"

    payload = {
        "status": "publish",
        "title": f"Reservation - {slots.get('customer_name', 'Guest')} - {slots.get('date', '')} {slots.get('time', '')}",
        "meta": {
            "party_size": str(slots.get("party_size", "")),
            "date": str(slots.get("date", "")),
            "time": str(slots.get("time", "")),
            "seating_preference": str(slots.get("seating_preference", "")),
            "occasion": str(slots.get("occasion", "")),
            "notes": str(slots.get("notes", "")),
            "customer_name": str(slots.get("customer_name", "")),
            "phone": str(slots.get("phone", "")),
            "call_sid": str(slots.get("call_sid", "")),
        },
    }

    resp = requests.post(endpoint, headers=_wp_auth_header(), json=payload, timeout=20)

    if resp.status_code < 200 or resp.status_code >= 300:
        raise HTTPException(
            status_code=502,
            detail=f"WordPress sync failed ({resp.status_code}): {resp.text}",
        )

    return resp.json()


@app.post("/vapi-webhook")
def vapi_webhook(payload: Dict[str, Any]):
    tool_call = _extract_tool_call(payload)
    function_obj = tool_call.get("function") or {}
    raw_args = function_obj.get("arguments")
    args = _parse_tool_arguments(raw_args)

    slots = {
        "party_size": args.get("party_size", ""),
        "date": args.get("date", ""),
        "time": args.get("time", ""),
        "seating_preference": args.get("seating_preference", ""),
        "occasion": args.get("occasion", ""),
        "notes": args.get("notes", ""),
        "customer_name": args.get("customer_name", ""),
        "phone": args.get("phone", ""),
        "call_sid": payload.get("call", {}).get("id", ""),
    }

    calendar_id = (
        args.get("calendar_id")
        or payload.get("calendar_id")
        or DEFAULT_CALENDAR_ID
    )

    if not calendar_id:
        raise HTTPException(
            status_code=500,
            detail="No CALENDAR_ID provided in payload or environment",
        )

    start_dt = _parse_date_time(str(slots["date"]), str(slots["time"]))
    end_dt = start_dt + timedelta(minutes=DEFAULT_RESERVATION_DURATION_MINUTES)

    calendar_event = _create_calendar_event(calendar_id, slots, start_dt, end_dt)
    wp_reservation = _sync_wordpress_reservation(slots)

    tool_call_id = tool_call.get("id") or tool_call.get("toolCallId") or ""
    guest_name = slots.get("customer_name") or "Guest"
    time_text = slots.get("time") or start_dt.strftime("%H:%M")

    return {
        "results": [
            {
                "toolCallId": tool_call_id,
                "result": f"Reservation confirmed for {guest_name} at {time_text}",
            }
        ],
        "meta": {
            "calendar_event_id": calendar_event.get("id", ""),
            "wordpress_reservation_id": wp_reservation.get("id", ""),
        },
    }
