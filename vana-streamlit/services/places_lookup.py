"""Google Places lookup helper used by the Streamlit editor.

Implements `lookup_place(query, api_key, bias_lat, bias_lng)` which calls
the Places API (v1 searchText) and returns a normalized place dict or None.
"""
from __future__ import annotations

import requests
from typing import Optional, Dict


PLACES_SEARCH_URL = "https://places.googleapis.com/v1/places:searchText"


def lookup_place(
    query: str,
    api_key: str,
    bias_lat: float = 27.5815,
    bias_lng: float = 77.6997,
) -> Optional[Dict]:
    """Lookup a place using Google Places Text Search (v1).

    Args:
        query: freeform text to search (e.g. "Rupa Sanatana Math Vrindavan").
        api_key: Google Places API key (from secrets).
        bias_lat, bias_lng: approximate bias center (latitude, longitude).

    Returns:
        dict with keys: name, formatted, lat, lng, place_id or None on failure.
    """
    if not query or not api_key:
        return None

    headers = {
        "Content-Type": "application/json",
        "X-Goog-Api-Key": api_key,
        "X-Goog-FieldMask": (
            "places.displayName,places.formattedAddress,places.location,places.id"
        ),
    }

    body = {
        "textQuery": query,
        "locationBias": {
            "circle": {
                "center": {"latitude": float(bias_lat), "longitude": float(bias_lng)},
                "radius": 50000.0,
            }
        },
        "maxResultCount": 3,
    }

    try:
        resp = requests.post(PLACES_SEARCH_URL, json=body, headers=headers, timeout=10)
        resp.raise_for_status()
        data = resp.json()
        places = data.get("places", [])
        if not places:
            return None

        p = places[0]
        loc = p.get("location", {})
        name = None
        # displayName may be structured; try common keys
        dn = p.get("displayName") or {}
        if isinstance(dn, dict):
            name = dn.get("text") or dn.get("displayName")
        else:
            name = dn or p.get("name")

        formatted = p.get("formattedAddress", "") or p.get("formatted", "")

        return {
            "name": name or query,
            "formatted": formatted,
            "lat": loc.get("latitude"),
            "lng": loc.get("longitude"),
            "place_id": p.get("id", ""),
        }
    except Exception:
        return None


__all__ = ["lookup_place"]
