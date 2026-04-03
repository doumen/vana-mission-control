#!/usr/bin/env python3
"""
tools/edit_timeline.py

CLI for editing a Schema 6.1 timeline JSON file.

Usage: tools/edit_timeline.py TIMELINE_FILE <command> [options]

Commands:
  list-days
  list-events --day DAYKEY
  show-event --event EVENTKEY
  add-vod --event EVENTKEY --vod JSON_STRING
  remove-vod --event EVENTKEY --vod-key VODKEY
  set-vods --event EVENTKEY --vods-file FILE

Only uses Python standard library.
"""

from __future__ import annotations
import argparse
import json
import sys
import shutil
from typing import Any, Dict, List, Optional, Tuple

KeyNames = ("dayKey", "day_key", "key", "id", "dayId", "day_id")
EventKeyNames = ("eventKey", "event_key", "key", "id", "eventId", "event_id")
VodKeyNames = ("vodKey", "vod_key", "key", "id")


def eprint(*args, **kwargs):
    print(*args, file=sys.stderr, **kwargs)


def load_json_file(path: str) -> Any:
    try:
        with open(path, "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception as exc:
        eprint(f"Failed to read/parse JSON file: {exc}")
        sys.exit(1)


def write_json_file_with_backup(path: str, data: Any) -> None:
    bak = path + ".bak"
    try:
        shutil.copy2(path, bak)
    except Exception:
        # If original doesn't exist or copy fails, still attempt to write but warn.
        eprint("Warning: unable to create .bak (continuing to write new file).")
    try:
        with open(path, "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
    except Exception as exc:
        eprint(f"Failed to write JSON file: {exc}")
        sys.exit(1)


def find_days(timeline: Any) -> Dict[str, Any]:
    """
    Return mapping day_key -> day_object
    Looks for top-level 'days' key (dict or list) or top-level timeline items.
    """
    days_map: Dict[str, Any] = {}
    if isinstance(timeline, dict):
        if "days" in timeline:
            days = timeline["days"]
            if isinstance(days, dict):
                for k, v in days.items():
                    days_map[str(k)] = v
                return days_map
            elif isinstance(days, list):
                for idx, d in enumerate(days):
                    key = _extract_key_from_obj(d, KeyNames) or f"index:{idx}"
                    days_map[str(key)] = d
                return days_map
        # If top-level itself is a day mapping
        # fallback: any top-level keys that look like day entries? we won't guess further.
    # If not dict or no days, return empty
    return days_map


def _extract_key_from_obj(obj: Any, names: Tuple[str, ...]) -> Optional[str]:
    if not isinstance(obj, dict):
        return None
    for name in names:
        if name in obj:
            try:
                return str(obj[name])
            except Exception:
                return None
    return None


def iter_events(timeline: Any) -> List[Tuple[str, Dict[str, Any]]]:
    """
    Return list of (event_key, event_obj) found across timeline days.
    """
    found: List[Tuple[str, Dict[str, Any]]] = []
    days = find_days(timeline)
    if days:
        for day_key, day_obj in days.items():
            events = _extract_events_from_day(day_obj)
            for e in events:
                key = _extract_key_from_obj(e, EventKeyNames) or "<unknown>"
                found.append((key, e))
    else:
        # Fallback: if timeline has top-level 'events' list
        if isinstance(timeline, dict) and "events" in timeline and isinstance(timeline["events"], list):
            for e in timeline["events"]:
                key = _extract_key_from_obj(e, EventKeyNames) or "<unknown>"
                found.append((key, e))
    return found


def _extract_events_from_day(day_obj: Any) -> List[Dict[str, Any]]:
    if not isinstance(day_obj, dict):
        return []
    # Common keys: 'events', 'items', 'visits'
    for k in ("events", "items", "visits"):
        if k in day_obj and isinstance(day_obj[k], list):
            return [e for e in day_obj[k] if isinstance(e, dict)]
    return []


def find_event(timeline: Any, event_key: str) -> Tuple[Optional[Dict[str, Any]], Optional[str]]:
    """
    Returns (event_obj, day_key) if found, else (None, None).
    """
    days = find_days(timeline)
    if days:
        for day_k, day_obj in days.items():
            events = _extract_events_from_day(day_obj)
            for e in events:
                k = _extract_key_from_obj(e, EventKeyNames)
                if k == event_key:
                    return e, day_k
    # Fallback top-level events
    if isinstance(timeline, dict) and "events" in timeline and isinstance(timeline["events"], list):
        for e in timeline["events"]:
            k = _extract_key_from_obj(e, EventKeyNames)
            if k == event_key:
                return e, None
    return None, None


def ensure_event_has_vods(event: Dict[str, Any]) -> None:
    if "vods" not in event or not isinstance(event["vods"], list):
        event["vods"] = []


def find_vod_index(vods: List[Any], vod_key: str) -> Optional[int]:
    for idx, v in enumerate(vods):
        if isinstance(v, dict):
            for name in VodKeyNames:
                if name in v and str(v[name]) == vod_key:
                    return idx
        else:
            # If vod is a primitive (string) compare directly
            if str(v) == vod_key:
                return idx
    return None


def cmd_list_days(args):
    timeline = load_json_file(args.timeline_file)
    days = find_days(timeline)
    if not days:
        eprint("No days found in timeline.")
        sys.exit(1)
    for k in days.keys():
        print(k)


def cmd_list_events(args):
    timeline = load_json_file(args.timeline_file)
    days = find_days(timeline)
    if not days:
        eprint("No days found in timeline.")
        sys.exit(1)
    if args.day not in days:
        eprint(f"Day not found: {args.day}")
        sys.exit(1)
    events = _extract_events_from_day(days[args.day])
    for e in events:
        k = _extract_key_from_obj(e, EventKeyNames) or "<unknown>"
        print(k)


def cmd_show_event(args):
    timeline = load_json_file(args.timeline_file)
    event, _ = find_event(timeline, args.event)
    if event is None:
        eprint(f"Event not found: {args.event}")
        sys.exit(1)
    print(json.dumps(event, ensure_ascii=False, indent=2))


def cmd_add_vod(args):
    timeline = load_json_file(args.timeline_file)
    event, day_key = find_event(timeline, args.event)
    if event is None:
        eprint(f"Event not found: {args.event}")
        sys.exit(1)
    try:
        vod_obj = json.loads(args.vod)
    except Exception as exc:
        eprint(f"Invalid VOD JSON: {exc}")
        sys.exit(1)
    if not isinstance(vod_obj, (dict, list, str, int, float, bool)) and vod_obj is not None:
        eprint("VOD must be a valid JSON value (object, array, or primitive).")
        sys.exit(1)
    ensure_event_has_vods(event)
    event["vods"].append(vod_obj)
    write_json_file_with_backup(args.timeline_file, timeline)
    print(f"Added VOD to event {args.event} and saved {args.timeline_file}")


def cmd_remove_vod(args):
    timeline = load_json_file(args.timeline_file)
    event, _ = find_event(timeline, args.event)
    if event is None:
        eprint(f"Event not found: {args.event}")
        sys.exit(1)
    ensure_event_has_vods(event)
    idx = find_vod_index(event["vods"], args.vod_key)
    if idx is None:
        eprint(f"VOD not found: {args.vod_key}")
        sys.exit(1)
    removed = event["vods"].pop(idx)
    write_json_file_with_backup(args.timeline_file, timeline)
    print(f"Removed VOD {args.vod_key} from event {args.event} and saved {args.timeline_file}")


def cmd_set_vods(args):
    timeline = load_json_file(args.timeline_file)
    event, _ = find_event(timeline, args.event)
    if event is None:
        eprint(f"Event not found: {args.event}")
        sys.exit(1)
    try:
        with open(args.vods_file, "r", encoding="utf-8") as f:
            vods = json.load(f)
    except Exception as exc:
        eprint(f"Failed to read/parse vods file: {exc}")
        sys.exit(1)
    if not isinstance(vods, list):
        eprint("VODs file must contain a JSON array (list) of VODs.)")
        sys.exit(1)
    event["vods"] = vods
    write_json_file_with_backup(args.timeline_file, timeline)
    print(f"Set {len(vods)} vod(s) on event {args.event} and saved {args.timeline_file}")


def build_parser():
    p = argparse.ArgumentParser(
        description="Edit a Schema 6.1 timeline JSON file. Creates a .bak backup when writing.",
    )
    p.add_argument("timeline_file", help="Path to timeline JSON file")
    sub = p.add_subparsers(dest="command", required=True)

    sub.add_parser("list-days", help="List day keys")

    p_list_events = sub.add_parser("list-events", help="List events for a day")
    p_list_events.add_argument("--day", required=True, help="Day key (as listed by list-days)")

    p_show = sub.add_parser("show-event", help="Show event JSON")
    p_show.add_argument("--event", required=True, help="Event key")

    p_add = sub.add_parser("add-vod", help="Add a VOD object to an event (JSON string)")
    p_add.add_argument("--event", required=True, help="Event key")
    p_add.add_argument(
        "--vod",
        required=True,
        help='VOD as JSON string, e.g. \'{"vodKey":"v1","url":"..."}\'',
    )

    p_remove = sub.add_parser("remove-vod", help="Remove a VOD from an event by vod-key")
    p_remove.add_argument("--event", required=True, help="Event key")
    p_remove.add_argument("--vod-key", required=True, help="VOD key to remove (matches vodKey/key/id)")

    p_set = sub.add_parser("set-vods", help="Replace an event's vods with a JSON array from a file")
    p_set.add_argument("--event", required=True, help="Event key")
    p_set.add_argument("--vods-file", required=True, help="Path to JSON file containing an array of vods")

    return p


def main():
    parser = build_parser()
    args = parser.parse_args()
    cmd = args.command
    if cmd == "list-days":
        cmd_list_days(args)
    elif cmd == "list-events":
        cmd_list_events(args)
    elif cmd == "show-event":
        cmd_show_event(args)
    elif cmd == "add-vod":
        cmd_add_vod(args)
    elif cmd == "remove-vod":
        cmd_remove_vod(args)
    elif cmd == "set-vods":
        cmd_set_vods(args)
    else:
        eprint("Unknown command")
        sys.exit(1)


if __name__ == "__main__":
    main()
