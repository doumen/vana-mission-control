import json

with open("visit-processed.json", "r", encoding="utf-8") as f:
    d = json.load(f)

print("=== INDEX: EVENTS ===")
for k, v in d["index"]["events"].items():
    print(f"  {k}")
    print(f"    has_katha: {v.get('has_katha')}")
    print(f"    katha_id:  {v.get('katha_id')}")

print()
print("=== INDEX: KATHAS ===")
for k, v in d["index"]["kathas"].items():
    print(f"  katha_id {k}: {v.get('title_pt')}")
    print(f"    sources: {v.get('sources')}")
