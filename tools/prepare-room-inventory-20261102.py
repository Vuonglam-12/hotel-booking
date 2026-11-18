"""Prepare user-authorized managed inventory, preserving unknown prices and all existing rooms."""
import json,pathlib
root=pathlib.Path(__file__).resolve().parents[1]
out=root/'database/data/room-inventory-configuration-2026-11-02.json'
if out.exists():
    print(json.dumps({'preserved_existing_manifest':True}));raise SystemExit
audit=json.loads((root/'storage/app/private/room-admin-update-20261102/audit-before.json').read_text(encoding='utf8'))
records=[]
for row in audit['types']:
    if row['total_rooms']!=0:continue
    assert row['capacity'] is not None and row['status']=='active'
    records.append({**row,'configured_total_rooms':3})
manifest={'configured_on':'2026-11-02','source_kind':'user_authorized_project_inventory','source_uri':'project://docs/itinerary-approved-requirements.md#2026-11-02','rooms_per_empty_type':3,'records':records}
out.write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'types':len(records),'rooms_to_add':sum(row['configured_total_rooms'] for row in records)}))
