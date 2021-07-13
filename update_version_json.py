#!/usr/bin/env python3
import json
import os
import sys

try:
    with open('/app/version.json', 'r') as f:
        circle_data = json.load(f)
        f.close()
except IOError:
    circle_data = {}

app_data = dict()
app_data = {
    'upstream_arcanist_source': 'https://github.com/phacility/arcanist',
    'upstream_arcanist_version': os.getenv('ARCANIST_GIT_SHA', None),
    'upstream_phabricator_source': 'https://github.com/phacility/phabricator',
    'upstream_phabricator_version': os.getenv('PHABRICATOR_GIT_SHA', None),
}
app_data.update(circle_data)
try:
    with open('/app/version.json', 'w') as f:
        json.dump(app_data, f)
except IOError:
    sys.exit()
