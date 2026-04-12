import services.yt_discovery as yd


class FakeResp:
    def __init__(self, j):
        self._j = j

    def raise_for_status(self):
        return None

    def json(self):
        return self._j


def test_search_videos_for_day(monkeypatch):
    # Prepare fake search API response
    search_json = {
        "items": [
            {
                "id": {"videoId": "VID123"},
                "snippet": {"title": "Mangala Arati - Vrindavan", "publishedAt": "2026-02-21T05:30:00Z"},
            }
        ]
    }
    # Prepare fake videos API response
    video_json = {
        "items": [
            {
                "id": "VID123",
                "contentDetails": {"duration": "PT1H30M"},
                "snippet": {"publishedAt": "2026-02-21T05:30:00Z", "title": "Mangala Arati - Vrindavan"},
            }
        ]
    }

    def fake_get(url, params=None, timeout=None):
        if url.startswith(yd.YT_SEARCH_URL):
            return FakeResp(search_json)
        if url.startswith(yd.YT_VIDEOS_URL):
            return FakeResp(video_json)
        raise RuntimeError("Unexpected URL")

    monkeypatch.setattr('services.yt_discovery.requests.get', fake_get)

    res = yd.search_videos_for_day(api_key="KEY", day_key="2026-02-21", channel_id="CHAN")
    assert isinstance(res, list)
    assert len(res) == 1
    it = res[0]
    assert it['video_id'] == 'VID123'
    assert 'inferred_type' in it
    assert 'duration_s' in it and it['duration_s'] > 0
 