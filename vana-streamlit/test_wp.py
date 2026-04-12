from api.wp_client import list_visits_rest, get_visit_timeline

visits = list_visits_rest(per_page=3)
for v in visits:
    print("ID:", v["id"], "| tour_key:", v["tour_key"], "| schema:", v["schema_ver"])

print()

tl = get_visit_timeline(359)
print("Timeline 359:", len(tl.get("days", [])), "dias | schema:", tl.get("schema_version"))
