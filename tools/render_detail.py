"""Render one material study independently, keeping each CI job bounded.

blender -b -t 4 --python-exit-code 1 -P tools/render_detail.py -- --detail oak
Uses the exact same geometry/material definitions as the architectural studio.
"""
import argparse
import hashlib
import json
from pathlib import Path
import runpy
import sys

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--detail', choices=['oak', 'limestone', 'limewash'], required=True)
parser.add_argument('--output', default='rendered/images')
parser.add_argument('--width', type=int, default=1600)
parser.add_argument('--samples', type=int, default=96)
args = parser.parse_args(sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else [])
if args.width < 320 or args.samples < 1:
    parser.error('Invalid render dimensions or sample count')
# The shared module parses Blender's post-separator arguments on import.
sys.argv = ['blender', '--', '--project', '0', '--state', 'after', '--width', str(args.width), '--samples', str(args.samples), '--output', args.output]
studio = runpy.run_path(str(Path(__file__).with_name('render_architecture.py')))
import bpy
studio['setup']()
studio['kitchen'](True)
studio['organize_scene']()
views = {
    'oak': ((-.85,-2.5,1.3),(.2,.2,.62),50),
    'limestone': ((1.8,-1.6,1.65),(.65,.35,.98),55),
    'limewash': ((-.75,-.4,1.83),(-.5,3.2,1.65),48),
}
cam = studio['camera'](*views[args.detail])
Path(args.output).mkdir(parents=True, exist_ok=True)
image = studio['render_file']('material-' + args.detail + '.jpg')
meta = {'revision': 2, 'detail': args.detail, 'blender': bpy.app.version_string, 'camera': cam, 'samples': args.samples, 'resolution': [args.width, round(args.width*2/3)], 'render': image}
(Path(args.output) / ('material-' + args.detail + '.json')).write_text(json.dumps(meta, indent=2) + '\n')
print('NORTHLINE_DETAIL_PASS ' + args.detail)
