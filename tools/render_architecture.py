"""NORTHLINE / Material & Space — reproducible architectural studio, revision 2.

Blender 4.2+; no add-ons, textures, downloaded models or network required.
    blender -b -t 4 --python-exit-code 1 -P tools/render_architecture.py -- \
      --project 0 --samples 96 --output rendered --blend-dir models

Metres throughout. Existing/proposed states share their camera and site datum.
All six houses are fictional design studies, not completed client commissions.
"""
import argparse
import hashlib
import json
import math
import os
from pathlib import Path
import random
import sys
import bpy
from mathutils import Vector

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--project', type=int, choices=range(6), default=0)
parser.add_argument('--output', default='theme/northline/assets/images')
parser.add_argument('--samples', type=int, default=96)
parser.add_argument('--width', type=int, default=1920)
parser.add_argument('--state', choices=['both', 'after', 'before'], default='both')
parser.add_argument('--blend-dir', default='')
parser.add_argument('--details', action='store_true')
args = parser.parse_args(sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else [])
if args.width < 320 or args.samples < 1:
    parser.error('width must be at least 320; samples must be positive')
NAMES = ['birch-house', 'tidal-house', 'alder-house', 'orchard-house', 'courtyard-house', 'harbour-house']
M = {}


def material(name, color, rough=.5, metal=0, transmission=0, emission=0):
    m = bpy.data.materials.new(name)
    m.diffuse_color = (*color, 1)
    m.use_nodes = True
    p = m.node_tree.nodes.get('Principled BSDF')
    for key, value in [('Base Color', (*color, 1)), ('Roughness', rough), ('Metallic', metal), ('Transmission Weight', transmission)]:
        p.inputs[key].default_value = value
    if emission:
        p.inputs['Emission Color'].default_value = (*color, 1)
        p.inputs['Emission Strength'].default_value = emission
    return m


def surface(name, a, b, scale=8, stretch=(1, 1, 1), rough=.55, relief=.05, grain=False):
    """Small-scale relief, broad pigment variation, and per-object tonal variation."""
    m = material(name, a, rough)
    n, links = m.node_tree.nodes, m.node_tree.links
    p = n.get('Principled BSDF')
    uv = n.new('ShaderNodeTexCoord')
    vec = n.new('ShaderNodeVectorMath'); vec.operation = 'MULTIPLY'; vec.inputs[1].default_value = stretch
    links.new(uv.outputs['Generated'], vec.inputs[0])
    noise = n.new('ShaderNodeTexNoise'); noise.inputs['Scale'].default_value = scale; noise.inputs['Detail'].default_value = 3.8
    links.new(vec.outputs[0], noise.inputs['Vector'])
    ramp = n.new('ShaderNodeValToRGB')
    ramp.color_ramp.elements[0].position = .2; ramp.color_ramp.elements[0].color = (*a, 1)
    ramp.color_ramp.elements[1].position = .8; ramp.color_ramp.elements[1].color = (*b, 1)
    links.new(noise.outputs['Fac'], ramp.inputs[0])
    info = n.new('ShaderNodeObjectInfo')
    vary = n.new('ShaderNodeMapRange'); vary.inputs['To Min'].default_value = .86; vary.inputs['To Max'].default_value = 1.03
    links.new(info.outputs['Random'], vary.inputs['Value'])
    mix = n.new('ShaderNodeMixRGB'); mix.blend_type = 'MULTIPLY'; mix.inputs[0].default_value = 1
    links.new(ramp.outputs['Color'], mix.inputs[1]); links.new(vary.outputs[0], mix.inputs[2]); links.new(mix.outputs[0], p.inputs['Base Color'])
    bump = n.new('ShaderNodeBump'); bump.inputs['Strength'].default_value = relief; bump.inputs['Distance'].default_value = .012 if grain else .004
    links.new(noise.outputs['Fac'], bump.inputs['Height']); links.new(bump.outputs[0], p.inputs['Normal'])
    return m


def mesh_object(name, vertices, faces, mat, loc=(0,0,0)):
    mesh=bpy.data.meshes.new(name); mesh.from_pydata(vertices, [], faces); mesh.update()
    o=bpy.data.objects.new(name,mesh); bpy.context.collection.objects.link(o); o.location=loc
    if mat:mesh.materials.append(mat)
    return o


def box(name, loc, dims, mat, bevel=.008, segments=3):
    x,y,z=[v/2 for v in dims]
    verts=[(-x,-y,-z),(x,-y,-z),(x,y,-z),(-x,y,-z),(-x,-y,z),(x,-y,z),(x,y,z),(-x,y,z)]
    o=mesh_object(name,verts,[(0,3,2,1),(4,5,6,7),(0,1,5,4),(1,2,6,5),(2,3,7,6),(3,0,4,7)],mat,loc)
    if bevel:
        mod=o.modifiers.new('Machined edge / light-catching arris','BEVEL'); mod.width=bevel; mod.segments=segments
        o.modifiers.new('Weighted face normals','WEIGHTED_NORMAL')
    return o


def cylinder(name, loc, radius, depth, mat, vertices=32):
    verts=[(radius*math.cos(i*math.tau/vertices),radius*math.sin(i*math.tau/vertices),z) for z in [-depth/2,depth/2] for i in range(vertices)]
    faces=[tuple(reversed(range(vertices))),tuple(range(vertices,vertices*2))]
    faces.extend((i,(i+1)%vertices,(i+1)%vertices+vertices,i+vertices) for i in range(vertices))
    o=mesh_object(name,verts,faces,mat,loc)
    mod=o.modifiers.new('Soft edge','BEVEL');mod.width=min(.006,radius*.15);mod.segments=3
    o.modifiers.new('Weighted normals','WEIGHTED_NORMAL')
    for f in list(o.data.polygons)[2:]:f.use_smooth=True
    return o


def rod(name, start, end, radius, mat, taper=.75):
    a,b=Vector(start),Vector(end);length=(b-a).length;segments=16
    verts=[(r*math.cos(i*math.tau/segments),r*math.sin(i*math.tau/segments),z) for r,z in [(radius,-length/2),(radius*taper,length/2)] for i in range(segments)]
    faces=[tuple(reversed(range(segments))),tuple(range(segments,segments*2))]
    faces.extend((i,(i+1)%segments,(i+1)%segments+segments,i+segments) for i in range(segments))
    o=mesh_object(name,verts,faces,mat,(a+b)/2);o.rotation_euler=(b-a).to_track_quat('Z','Y').to_euler()
    for f in list(o.data.polygons)[2:]:f.use_smooth=True
    return o


def curve(name, points, radius, mat, cyclic=False):
    c = bpy.data.curves.new(name, 'CURVE'); c.dimensions = '3D'; c.resolution_u = 12; c.bevel_depth = radius; c.bevel_resolution = 3
    s = c.splines.new('BEZIER'); s.bezier_points.add(len(points)-1); s.use_cyclic_u = cyclic
    for p, co in zip(s.bezier_points, points): p.co = co; p.handle_left_type = 'AUTO'; p.handle_right_type = 'AUTO'
    o = bpy.data.objects.new(name, c); bpy.context.collection.objects.link(o); o.data.materials.append(mat)
    return o


def ellipsoid(name, loc, dims, mat):
    segments,rings=24,12
    verts=[(dims[0]*math.sin(j*math.pi/rings)*math.cos(i*math.tau/segments),dims[1]*math.sin(j*math.pi/rings)*math.sin(i*math.tau/segments),dims[2]*math.cos(j*math.pi/rings)) for j in range(rings+1) for i in range(segments)]
    faces=[(j*segments+i,j*segments+(i+1)%segments,(j+1)*segments+(i+1)%segments,(j+1)*segments+i) for j in range(rings) for i in range(segments)]
    o=mesh_object(name,verts,faces,mat,loc)
    for f in o.data.polygons:f.use_smooth=True
    return o


def lathe(name, loc, profile, mat, segments=64):
    """Revolved hollow profiles, not capped cylinders: ceramic rims and light shades."""
    verts = [(r*math.cos(t*2*math.pi/segments), r*math.sin(t*2*math.pi/segments), z) for r,z in profile for t in range(segments)]
    faces = []
    for row in range(len(profile)-1):
        for t in range(segments):
            j=(t+1)%segments; faces.append((row*segments+t, row*segments+j, (row+1)*segments+j, (row+1)*segments+t))
    mesh=bpy.data.meshes.new(name); mesh.from_pydata(verts, [], faces); mesh.update()
    o=bpy.data.objects.new(name,mesh); bpy.context.collection.objects.link(o); o.location=loc; o.data.materials.append(mat)
    for f in mesh.polygons: f.use_smooth=True
    return o


def light(name, loc, target, power, size=3, color=(1,.93,.82)):
    data=bpy.data.lights.new(name,'AREA');o=bpy.data.objects.new(name,data);bpy.context.collection.objects.link(o);o.location=loc; o.data.energy=power; o.data.shape='RECTANGLE'; o.data.size=size; o.data.size_y=size*.7; o.data.color=color
    o.rotation_euler=(Vector(target)-o.location).to_track_quat('-Z','Y').to_euler()
    # Softboxes must not appear as giant white discs in glazing reflections.
    o.visible_glossy=False; o.visible_transmission=False
    return o


def camera(loc, target, lens=35):
    data=bpy.data.cameras.new('Matched architectural camera');c=bpy.data.objects.new('Matched architectural camera',data);bpy.context.collection.objects.link(c);c.location=loc; c.rotation_euler=(Vector(target)-c.location).to_track_quat('-Z','Y').to_euler()
    c.data.lens=lens; c.data.clip_end=200; bpy.context.scene.camera=c
    return {'location':list(loc), 'target':list(target), 'lens_mm':lens, 'sensor_mm':c.data.sensor_width}


def setup():
    for obj in list(bpy.data.objects):bpy.data.objects.remove(obj,do_unlink=True)
    for pool in [bpy.data.meshes,bpy.data.curves,bpy.data.materials,bpy.data.lights,bpy.data.cameras,bpy.data.worlds]:
        for item in list(pool):
            if item.users==0: pool.remove(item)
    s=bpy.context.scene; s.unit_settings.system='METRIC'; s.render.engine='CYCLES'; s.cycles.samples=args.samples; s.cycles.use_denoising=True
    s.cycles.adaptive_threshold=.025; s.cycles.max_bounces=10; s.cycles.transmission_bounces=8; s.cycles.seed=417+args.project
    s.render.resolution_x=args.width; s.render.resolution_y=round(args.width*2/3); s.render.resolution_percentage=100
    s.render.image_settings.file_format='JPEG'; s.render.image_settings.quality=90
    s.view_settings.view_transform='AgX'; s.view_settings.look='AgX - Medium High Contrast'; s.view_settings.exposure=-.45
    w=bpy.data.worlds.new('Atlantic / low afternoon daylight'); w.use_nodes=True; s.world=w
    sky=w.node_tree.nodes.new('ShaderNodeTexSky'); sky.sky_type='NISHITA' if 'NISHITA' in sky.bl_rna.properties['sky_type'].enum_items.keys() else 'MULTIPLE_SCATTERING'; sky.sun_elevation=math.radians(23); sky.sun_rotation=math.radians(128)
    setattr(sky, 'dust_density' if hasattr(sky,'dust_density') else 'aerosol_density', 1.4); w.node_tree.links.new(sky.outputs[0],w.node_tree.nodes.get('Background').inputs[0]); w.node_tree.nodes.get('Background').inputs[1].default_value=.24
    data=bpy.data.lights.new('Afternoon sunlight','SUN');sun=bpy.data.objects.new('Afternoon sunlight',data);bpy.context.collection.objects.link(sun);sun.location=(4,-5,7); sun.data.energy=1.35; sun.data.angle=.075; sun.rotation_euler=(.62,-.5,-.67)
    M.clear()
    M.update({
        'oak':surface('Oak / quarter-sawn, matte hardwax',(.13,.068,.031),(.34,.225,.116),3,(6,1,62),.4,.11,True),
        'floor':surface('Oak / long-grain staggered boards',(.2,.126,.067),(.39,.29,.17),4,(16,.7,1),.48,.1,True),
        'walnut':surface('Cedar / vertical sawn grain',(.057,.026,.012),(.19,.105,.044),3,(10,1,80),.48,.1,True),
        'stone':surface('Limestone / honed shellstone',(.48,.445,.355),(.7,.655,.56),28,(1,1,1),.5,.11),
        'plaster':surface('Limewash / layered mineral pigment',(.54,.51,.435),(.79,.745,.655),7,(1,1,1),.88,.14),
        'linen':surface('Linen / woven upholstery',(.38,.365,.30),(.68,.645,.55),180,(1,1,1),.92,.38),
        'dark':material('Bronze / dark anodised metal',(.018,.026,.024),.3,.73),
        'brass':material('Brass / satin brushed finish',(.38,.235,.085),.3,.8),
        'green':material('Olive / eggshell joinery lacquer',(.105,.145,.095),.47),
        'white':material('Ceramic / warm porcelain glaze',(.75,.725,.655),.24),
        'glass':material('Glazing / low iron',(.96,.98,1),.025,0,1),
        'soil':surface('Soil / planting bed',(.045,.035,.023),(.15,.13,.078),60,rough=1,relief=.6),
        'grass':surface('Meadow / mixed ground cover',(.065,.095,.04),(.19,.24,.105),60,rough=1,relief=.5),
        'leaves':material('Foliage / upper leaf',(.1,.18,.055),.7),
        'leaves2':material('Foliage / silver underside',(.22,.295,.12),.82),
        'opal':material('Opal / frosted light diffuser',(.8,.745,.58),.45,emission=.6),
        'rug':surface('Wool / flatweave',(.27,.25,.2),(.48,.44,.35),220,rough=1,relief=.5),
        'black':material('Appliance / smoked glass',(.011,.015,.018),.14,.3),
    })
    if args.project==5: M['linen']=surface('Harbour / blue-grey linen',(.11,.15,.16),(.28,.33,.32),180,rough=.92,relief=.35)


def tree(x,y,h=3,seed=1,pot=False):
    """Branch structure and 600+ individual pointed leaves; no spherical canopy blobs."""
    rng=random.Random(seed)
    if pot:
        lathe('Hand-thrown planter',(x,y,0),[(0,0),(.2,0),(.25,.04),(.285,.45),(.28,.48),(.255,.48),(.255,.44),(.22,.07),(0,.07)],M['stone'])
        cylinder('Planter soil',(x,y,.44),.251,.012,M['soil'])
    rod('Tree trunk',(x,y,.1),(x+.07,y,h*.91),.026 if pot else .07,M['walnut'],.28)
    # Reuse meshes for leaves; every leaf remains editable and lightweight.
    meshes=[]
    for mat in [M['leaves'],M['leaves2']]:
        mesh=bpy.data.meshes.new('Lanceolate leaf / folded midrib')
        mesh.from_pydata([(0,0,0),(-.027,.075,.008),(0,.17,0),(.027,.075,.008),(0,.075,.019)],[],[(0,1,4),(1,2,4),(2,3,4),(3,0,4)])
        mesh.materials.append(mat); meshes.append(mesh)
    for j in range(22 if not pot else 12):
        ang=j*2.399; z=h*(.36+.55*j/(22 if not pot else 12)); spread=(.85 if pot else h*.33)*(1-.4*j/22)
        end=(x+math.cos(ang)*spread,y+math.sin(ang)*spread,z+h*.14)
        start=(x+.03,y,z)
        curve('Branch / tapered twig',[start,((start[0]+end[0])*.5,(start[1]+end[1])*.5,z+.16),end],.004 if pot else .011,M['walnut'])
        for k in range(22 if pot else 62):
            t=rng.uniform(.25,1.1); spread2=.14 if pot else .32
            o=bpy.data.objects.new('Leaf / linked geometry',meshes[k%2]); bpy.context.collection.objects.link(o)
            o.location=(x+(end[0]-x)*t+rng.uniform(-spread2,spread2),y+(end[1]-y)*t+rng.uniform(-spread2,spread2),z+(end[2]-z)*t+rng.uniform(-spread2,spread2))
            o.rotation_euler=(rng.uniform(-.8,.8),rng.uniform(-.7,.7),ang+rng.uniform(-1.7,1.7)); sc=(.7 if pot else 1.85)*rng.uniform(.7,1.4); o.scale=(sc,sc,sc)


def glazing(x,y,z,w,h,side=False,divisions=3):
    box('Low-iron glass pane',(x,y,z),(.018,w,h) if side else (w,.018,h),M['glass'],0)
    for i in range(divisions+1):
        d=-w/2+i*w/divisions
        box('Thermally broken mullion',(x-.015,y+d,z) if side else (x+d,y-.015,z),(.075,.038,h+.08) if side else (.038,.075,h+.08),M['dark'],.004)
    for dz in [-h/2,h/2]:
        box('Glazing head / sill',(x,y,z+dz),(.085,w+.08,.045) if side else (w+.08,.085,.045),M['dark'],.003)
    # Perimeter gasket shadows describe the construction at close range.
    box('Recessed threshold',(x,y,z-h/2-.018),(.18,w+.12,.028) if side else (w+.12,.18,.028),M['stone'],.003)


def curtain(x,y,length=2.9,width=.72):
    verts=[]; faces=[]; nx,nz=40,20
    for j in range(nz+1):
        for i in range(nx+1):
            u=i/nx; v=j/nz; verts.append((x+u*width,y+.07*math.sin(u*math.pi*12)*(1+.12*v),3.2-v*length+.01*math.cos(u*12*math.pi)))
    for j in range(nz):
        for i in range(nx):
            a=j*(nx+1)+i; faces.append((a,a+1,a+nx+2,a+nx+1))
    mesh=bpy.data.meshes.new('Woven linen curtain folds'); mesh.from_pydata(verts,[],faces); mesh.update()
    o=bpy.data.objects.new('Curtain / weighted hem and irregular folds',mesh); bpy.context.collection.objects.link(o); o.data.materials.append(M['linen'])
    for f in mesh.polygons:f.use_smooth=True
    solid=o.modifiers.new('Fabric thickness','SOLIDIFY'); solid.thickness=.0015
    curve('Curtain hem',[(x+i/nx*width,y+.07*math.sin(i/nx*math.pi*12)*1.12,.3) for i in range(nx+1)],.005,M['linen'])


def shell(after):
    box('Floor substrate',(0,-.1,-.1),(7.4,7.4,.16),M['dark'],0)
    if after:
        for i in range(34):
            for j in range(-1,5):
                y=-3.72+j*1.7+(i%3)*.24
                if y>3.5:continue
                bottom=max(-3.8,y); top=min(3.6,y+1.695)
                if top>bottom:box('Staggered oak plank',(-3.59+i*.218,(bottom+top)/2,0),(.215,top-bottom,.045),M['floor'],.001)
    else:
        for i in range(15):
            for j in range(15): box('Existing ceramic floor',(-3.5+i*.5,-3.5+j*.5,0),(.495,.495,.04),M['stone'],.001)
    box('Left enclosing wall',(-3.75,0,1.7),(.2,7.4,3.4),M['plaster'])
    box('Rear solid wall',(-1.8,3.65,1.7),(3.85,.2,3.4),M['plaster'])
    box('Rear lintel',(1.95,3.65,3.25),(3.65,.2,.3),M['plaster'])
    glazing(1.95,3.63,1.58,3.55,3.05,divisions=2)
    glazing(3.68,-.08,1.65,7.3,3.18,True,4)
    box('Ceiling',(0,0,3.48),(7.6,7.5,.14),M['plaster'])
    for y in [-2.6,-.4,1.8]:box('Oak structural beam',(0,y,3.31),(7.4,.14,.23),M['oak'])
    box('Shadow-gap skirting',(-3.638,0,.065),(.028,7.2,.095),M['oak'],.003)
    box('Window terrace',(3.9,4.2,-.035),(13,4,.07),M['stone'])
    box('Garden meadow',(3,30,-.16),(240,240,.2),M['grass'],0)
    for i,(x,y,h) in enumerate([(-5,7,3.5),(-2,8,4),(2,9,3.8),(6,8,4.6),(8,5,3.5),(10,3,4.4)]):tree(x,y,h,200+i)
    # The curtain is architecture, not a graphic blur over a window.
    if after:curtain(.15,3.47,width=.46)
    light('Window daylight / hidden softbox',(3.35,1.9,2.8),(0,0,.5),260,3.3,(.83,.9,1))
    light('Camera-side bounce',(-1.5,-4,2.5),(0,1,1),100,4)


def cabinet(x,y,w=.65,after=True):
    finish=(M['green'] if args.project==3 else M['oak']) if after else M['walnut']
    box('Cabinet carcass',(x,y,.48),(w,.65,.85),M['dark'])
    box('Recessed plinth',(x,y+.015,.10),(w,.49,.16),M['dark'])
    for z,h in [(.36,.46),(.755,.28)]:
        box('Drawer front / 3mm reveal',(x,y-.344,z),(w-.006,.028,h),finish,.003)
        rod('Brushed brass edge pull',(x-w*.32,y-.37,z+h/2-.025),(x+w*.32,y-.37,z+h/2-.025),.005,M['brass'],1)


def stool(x,y,z=0):
    for dx in [-.145,.145]:
        for dy in [-.13,.13]:rod('Turned stool leg',(x+dx*1.35,y+dy*1.35,z+.03),(x+dx,y+dy,z+.66),.022,M['oak'],.82)
    cylinder('Stool seat underside',(x,y,z+.66),.225,.038,M['oak'])
    ellipsoid('Upholstered stool seat',(x,y,z+.699),(.223,.213,.04),M['linen'])
    curve('Steam-bent back rail',[(x-.205,y+.055,z+.69),(x-.245,y-.12,z+.98),(x,y-.22,z+1.065),(x+.245,y-.12,z+.98),(x+.205,y+.055,z+.69)],.025,M['oak'])
    for dx in [-.14,0,.14]:rod('Back support',(x+dx,y-.17,z+.73),(x+dx,y-.2,z+1.015),.009,M['oak'])
    curve('Footrest / dark bronze',[(x-.19,y-.17,z+.27),(x+.19,y-.17,z+.27),(x+.19,y+.17,z+.27),(x-.19,y+.17,z+.27)],.01,M['dark'],True)


def pendant(x,y,z=2.35,ceiling=3.4):
    rod('Pendant cable',(x,y,ceiling),(x,y,z+.15),.0035,M['dark'],1)
    cylinder('Ceiling rose',(x,y,ceiling-.025),.047,.025,M['brass'])
    lathe('Spun brass shade',(x,y,z),[(.025,.15),(.10,.13),(.235,.075),(.32,0),(.315,-.009),(.23,.057),(.095,.11),(.025,.13)],M['brass'])
    cylinder('Opal diffuser',(x,y,z-.006),.285,.012,M['opal'],64)
    light('Pendant pool / hidden emitter',(x,y,z-.045),(x,y,.6),22,.48)


def bowl(x,y,z):
    lathe('Thrown ceramic bowl',(x,y,z),[(0,0),(.075,0),(.11,.025),(.21,.12),(.213,.135),(.202,.14),(.185,.125),(.085,.028),(0,.022)],M['white'])
    for dx,dy in [(-.06,0),(.07,-.03),(.03,.05)]:ellipsoid('Pear',(x+dx,y+dy,z+.11),(.052,.05,.067),M['green'])


def kitchen(after):
    shell(after)
    for i in range(6):cabinet(-3.3+i*.63,3.18,.63,after)
    box('Continuous honed counter',(-1.68,3.11,.935),(3.93,.83,.065),M['stone'])
    box('Stone splashback',(-1.63,3.522,1.16),(3.99,.027,.43),M['stone'])
    finish=M['green'] if args.project==3 else M['oak']
    for x in [-3.2,-2.5]:
        box('Full-height pantry',(x,3.19,1.62),(.695,.66,3.08),M['dark'])
        for z,h in [(1.32,2.4),(2.865,.65)]:
            box('Pantry front',(x,2.838,z),(.685,.037,h),finish if after else M['walnut'],.004)
        rod('Pantry pull',(x+.22,2.794,1.15),(x+.22,2.794,1.65),.008,M['brass'],1)
    # Two real appliance doors, control strips, trim and dials.
    for z in [1.35,1.98]:
        box('Integrated oven housing',(-1.74,3.0,z),(.64,.35,.59),M['dark'])
        box('Oven ceramic glass',(-1.74,2.81,z-.04),(.564,.028,.41),M['black'])
        box('Oven control strip',(-1.74,2.808,z+.232),(.6,.034,.11),M['dark'])
        rod('Oven handle',(-1.98,2.759,z+.137),(-1.5,2.759,z+.137),.011,M['brass'],1)
        for x in [-1.94,-1.54]:
            dial=cylinder('Machined control dial',(x,2.778,z+.233),.025,.014,M['brass']); dial.rotation_euler[0]=math.pi/2
        box('Oven display',(-1.74,2.785,z+.234),(.115,.006,.033),M['black'],.002)
    box('Recessed cooking surface',(-.69,3.04,.974),(.7,.51,.016),M['black'],.007)
    for x in [-.88,-.5]:
        for y in [2.89,3.18]:
            curve('Induction ring',[(x+.09*math.cos(t*math.pi/4),y+.09*math.sin(t*math.pi/4),.984) for t in range(8)],.001,M['dark'],True)
    box('Plaster extractor hood',(-.66,3.30,2.64),(1.2,.64,1.05),M['plaster'],.065,6)
    box('Extractor shadow recess',(-.66,3.21,2.103),(1.01,.4,.016),M['dark'])
    ix,iy=.35,.2; length=3.2 if after else 1.9
    box('Island shadow plinth',(ix,iy,.08),(length-.15,.92,.13),M['dark'],.05)
    box('Island joinery body',(ix,iy,.49),(length,1.16,.78),finish if after else M['walnut'],.08,6)
    if after:
        for i in range(91):
            x=ix-length/2+.045+i*(length-.09)/90
            box('Reeded oak island front',(x,iy-.584,.505),(.024,.024,.735),finish,.009,5)
    top=box('Island limestone / solid slab',(ix,iy,.946),(length+.16,1.35,.072),M['stone'],.028,5)
    # Boolean sink opening, with radiused, physically hollow steel basin below.
    hole=box('Sink cutout tool',(ix+.48,iy+.11,.94),(.6,.43,.4),None,.025,5)
    bpy.context.view_layer.objects.active=hole
    bpy.ops.object.modifier_apply(modifier=hole.modifiers[0].name)
    bpy.context.view_layer.objects.active=top
    for edge in list(top.modifiers):bpy.ops.object.modifier_apply(modifier=edge.name)
    mod=top.modifiers.new('Undermount sink opening','BOOLEAN'); mod.operation='DIFFERENCE'; mod.object=hole
    bpy.ops.object.modifier_apply(modifier=mod.name); bpy.data.objects.remove(hole,do_unlink=True)
    box('Sink bowl bottom',(ix+.48,iy+.11,.755),(.57,.4,.025),M['dark'],.035,5)
    for dx in [-.291,.291]:box('Sink bowl sides',(ix+.48+dx,iy+.11,.835),(.019,.405,.19),M['dark'],.018,5)
    for dy in [-.206,.206]:box('Sink bowl walls',(ix+.48,iy+.11+dy,.835),(.6,.018,.19),M['dark'],.018,5)
    cylinder('Sink drain',(ix+.48,iy+.11,.773),.026,.004,M['brass'])
    fx,fy=ix+.48,iy+.44
    curve('Gooseneck mixer',[(fx,fy,.99),(fx,fy,1.3),(fx,fy-.03,1.39),(fx,fy-.17,1.41),(fx,fy-.25,1.33)],.017,M['brass'])
    cylinder('Mixer base',(fx,fy,.995),.026,.035,M['brass'])
    rod('Mixer lever',(fx+.08,fy,1.03),(fx+.08,fy,1.12),.01,M['brass'])
    for x in [-.7,.3,1.3] if after else [.3]:stool(x,iy-1.06)
    for x in [-.48,1.2]:pendant(x,iy,2.34)
    bowl(-.7,.33,1.0)
    board=box('End-grain cutting board',(-.78,3.02,.987),(.38,.27,.024),M['oak'],.045,8); board.rotation_euler[2]=.1
    lathe('Unglazed vase',(-.05,3.19,.978),[(0,0),(.09,0),(.145,.13),(.12,.32),(.059,.42),(.048,.42),(.05,.36),(.1,.17),(0,.02)],M['stone'])
    for i in range(5):curve('Dry branch',[(-.05,3.19,1.25),(-.03+i*.015,3.2,1.6),(-.18+i*.06,3.16,1.94-i*.02)],.002,M['walnut'])
    if after:
        tree(-2.8,.05,2.1,93,True)
        box('Floating shelf',(-.03,3.39,1.87),(.55,.28,.04),M['oak'])
        for x in [-.18,.02]:lathe('Ceramic cup',(x,3.32,1.89),[(0,0),(.045,0),(.05,.105),(.044,.109),(.039,.01),(0,.01)],M['white'])
    return camera((-2.78,-6.65,1.94),(.1,1.18,1.57),30)


def cushion(name,loc,dims,mat,rotation=0):
    o=box(name,loc,dims,mat,min(dims)*.28,6); o.rotation_euler[2]=rotation
    # Piping follows the cushion's thin axis and inherits later tilt/rotation.
    w,d,h=dims
    if w==min(dims):
        points=[(w*.43,-d*.4,-h*.4),(w*.43,d*.4,-h*.4),(w*.43,d*.4,h*.4),(w*.43,-d*.4,h*.4)]
    elif d==min(dims):
        points=[(-w*.4,-d*.43,-h*.4),(w*.4,-d*.43,-h*.4),(w*.4,-d*.43,h*.4),(-w*.4,-d*.43,h*.4)]
    else:
        points=[(-w*.4,-d*.4,h*.43),(w*.4,-d*.4,h*.43),(w*.4,d*.4,h*.43),(-w*.4,d*.4,h*.43)]
    piping=curve(name+' / tailored piping',points,.0025,mat,True);piping.parent=o
    return o


def lounge_chair(x,y):
    for dx in [-.3,.3]:
        for dy in [-.28,.28]:rod('Lounge chair tapered leg',(x+dx*1.1,y+dy*1.1,.04),(x+dx,y+dy,.5),.025,M['oak'])
    cushion('Lounge chair seat',(x,y,.48),(.73,.72,.16),M['linen'])
    back=cushion('Lounge chair back',(x,y+.27,.81),(.73,.16,.63),M['linen']); back.rotation_euler[0]=-.14
    for dx in [-.38,.38]:curve('Sculpted oak arm',[(x+dx,y-.29,.63),(x+dx,y-.08,.7),(x+dx,y+.35,.73)],.033,M['oak'])


def living(after):
    shell(after)
    if not after:box('Existing dividing wall',(-1.2,2.28,1.62),(4.8,.16,3.2),M['plaster'])
    box('Flatweave rug',(.1,.1,.035),(4.4,3.9,.014),M['rug'],.016)
    for i in range(100):
        x=-2.1+i*.044
        rod('Rug fringe',(x,-1.85,.047),(x,-1.91,.047),.0016,M['linen'],1)
    box('Sofa suspended oak plinth',(-1.5,.15,.19),(1.0,2.9,.19),M['oak'],.05,5)
    fabric=M['linen'] if after else M['walnut']
    box('Sofa upholstered back',(-1.86,.15,.70),(.25,2.98,.84),fabric,.105,6)
    for y in [-.83,.15,1.13]:
        cushion('Sofa seat cushion',(-1.4,y,.42),(.95,.935,.26),fabric)
        p=cushion('Feather-filled back cushion',(-1.7,y,.78),(.26,.88,.68),fabric);p.rotation_euler[1]=-.14
    for y in [-1.41,1.71]:box('Sofa curved arm',(-1.44,y,.57),(1.03,.2,.67),fabric,.095,6)
    # Sculptural low table with two drum supports and a rounded limestone top.
    box('Limestone low table',(.08,.12,.34),(1.32,1.6,.085),M['stone'],.1,8)
    for y in [-.4,.65]:cylinder('Table drum support',(.08,y,.17),.19,.31,M['stone'])
    for j in range(3):
        book=box('Linen-bound art book',(-.05,-.06,.407+j*.033),(.37,.47,.028),M['white'] if j%2 else M['green'],.002); book.rotation_euler[2]=.08*j
        box('Book page block',(-.05,-.061,.409+j*.033),(.356,.448,.021),M['stone'],.001)
    bowl(.38,.58,.391)
    for x,y in [(1.85,-.85),(2.03,1.2)]:lounge_chair(x,y)
    box('Limewash chimney',(-3.38,.18,1.66),(.56,2.7,3.32),M['plaster'],.022)
    box('Recessed firebox',(-3.089,.18,.67),(.024,1.25,.79),M['dark'])
    box('Limestone hearth',(-2.89,.18,.18),(.84,1.76,.11),M['stone'])
    for j in range(6):rod('Split firewood',(-3.07,-.23+j*.14,.31),(-2.97,-.22+j*.14,.48),.037,M['oak'])
    box('Oak framed artwork',(-3.072,.18,2.09),(.035,1.06,1.18),M['oak'])
    box('Woven canvas artwork',(-3.048,.18,2.09),(.012,.98,1.1),M['linen'])
    # Relief composition, genuinely raised from its woven ground.
    relief=ellipsoid('Abstract plaster relief',(-3.03,.06,2.18),(.035,.29,.34),M['plaster'])
    box('Low oak credenza',(1.1,3.07,.38),(2.5,.53,.67),M['oak'],.016)
    for x in [.3,1.1,1.9]:box('Credenza panel',(x,2.79,.4),(.79,.018,.56),M['oak'],.003)
    lathe('Studio vase',(.4,3.04,.725),[(0,0),(.1,0),(.17,.19),(.08,.39),(.06,.4),(.055,.38),(.13,.15),(0,.02)],M['white'])
    tree(2.95,2.57,2.3,54,True)
    cylinder('Lamp weighted foot',(2.73,-1.25,.055),.21,.045,M['dark'])
    curve('Floor lamp stem',[(2.73,-1.25,.08),(2.73,-1.25,1.7),(2.50,-1.25,1.86)],.012,M['brass'])
    lathe('Linen lampshade',(2.5,-1.25,1.53),[(.28,0),(.17,.35),(.165,.35),(.274,0)],M['linen'])
    return camera((-.3 if args.project==2 else 1.05,-6.72,1.97),(0,.58,1.43),30)


def grasses(x,y,seed):
    rng=random.Random(seed)
    for i in range(28):
        a=rng.random()*math.tau; r=rng.uniform(.02,.25); h=rng.uniform(.3,.85)
        curve('Ornamental grass blade',[(x+math.cos(a)*r,y+math.sin(a)*r,.04),(x+math.cos(a)*r*1.3,y+math.sin(a)*r*1.3,h*.75),(x+math.cos(a)*r*2.8,y+math.sin(a)*r*2.8,h)],.0024,M['leaves2'])


def exterior(after):
    box('Site datum',(0,3,-.22),(240,240,.3),M['grass'],0)
    box('Existing dwelling floor',(-2,4,.04),(7,4,.12),M['oak'])
    box('Existing dwelling rear wall',(-2,5.96,1.65),(7,.12,3.3),M['plaster'])
    for x in [-5.5,1.5]:box('Existing dwelling side wall',(x,4,1.65),(.12,4,3.3),M['plaster'])
    box('Existing facade below openings',(-2,2,.57),(7,.12,1.14),M['plaster'])
    box('Existing facade above openings',(-2,2,3.04),(7,.12,.52),M['plaster'])
    for a,b in [(-5.5,-4.94),(-3.66,-2.54),(-1.26,-.14),(1.14,1.5)]:
        box('Existing window pier',((a+b)/2,2,1.97),(b-a,.12,1.7),M['plaster'])
    for i in range(76):
        x=-5.46+i*.092;front=M['oak'] if args.project==4 else M['plaster']
        if any(abs(x-w)<.67 for w in [-4.3,-1.9,.5]):
            for z,h in [(.57,1.14),(3.04,.52)]:box('Timber facade board',(x,1.91,z),(.086,.035,h),front,.002)
        else:box('Timber facade board',(x,1.91,1.65),(.086,.035,3.3),front,.002)
    # Gable infill closes the former gap under the pitched roof.
    for x in [-5.51,1.51]:
        mesh=bpy.data.meshes.new('Gable infill');mesh.from_pydata([(x,2,3.3),(x,6,3.3),(x,4,4.5)],[],[(0,1,2)])
        o=bpy.data.objects.new('Closed gable',mesh);bpy.context.collection.objects.link(o);o.data.materials.append(M['plaster'])
    for side in [-1,1]:
        roof=box('Standing-seam roof',(-2,4+side*1.08,3.88),(7.6,2.55,.10),M['dark']); roof.rotation_euler[0]=-side*math.radians(30)
        for i in range(32):
            seam=box('Folded roof seam',(-5.69+i*.238,4+side*1.08,3.943),(.019,2.55,.028),M['dark'],.003);seam.rotation_euler[0]=-side*math.radians(30)
        rod('Rain gutter',(-5.75,4+side*2.18,3.3),(1.75,4+side*2.18,3.3),.04,M['dark'],1)
    for x in [-4.3,-1.9,.5]:glazing(x,1.928,1.98,1.24,1.57,divisions=2)
    rod('Rainwater downpipe',(-5.38,1.85,3.25),(-5.38,1.85,.1),.036,M['dark'],1)
    if after:
        box('Extension roof membrane',(1,-.15,3.02),(6.3,4.65,.07),M['dark'])
        box('Deep bronze roof fascia',(1,-.15,2.91),(6.3,4.65,.18),M['dark'])
        box('Cedar ceiling soffit',(1,-.15,2.801),(6.12,4.51,.04),M['walnut'])
        for i in range(33):box('Soffit linear batten',(-1.98+i*.188,-.15,2.769),(.03,4.45,.025),M['oak'],.003)
        box('Thermal slab',(1,-.15,.12),(6.15,4.51,.2),M['stone'])
        glazing(1,-2.397,1.52,6.1,2.65,divisions=5);glazing(4.056,-.15,1.52,4.42,2.65,True,3)
        box('Cedar extension wall',(-2.05,-.15,1.49),(.15,4.5,2.74),M['walnut'])
        for i in range(54):box('Vertical cedar rainscreen',(-2.15,-2.36+i*.083,1.51),(.045,.05,2.7),M['walnut'],.003)
        box('Dining table top',(1,-.1,.84),(2.15,1.03,.075),M['oak'],.07,8)
        for x in [.25,1.75]:box('Dining trestle',(x,-.1,.49),(.095,.72,.63),M['oak'],.013)
        for x in [.25,1,1.75]:
            for y in [-1.12,.91]:stool(x,y,-.13)
        for x in [.22,1.8]:pendant(x,-.1,2.23,ceiling=2.77)
        bowl(1,-.1,.885)
        light('Extension interior bounce',(1,-.1,2.64),(1,-.1,.2),210,2.7)
    else:
        box('Original conservatory plinth',(1,-.15,.33),(5.3,3.7,.49),M['plaster'])
        glazing(1,-2.025,1.48,5.3,1.76,divisions=6);glazing(3.66,-.15,1.48,3.7,1.76,True,4)
        roof=box('Original opaque roof',(1,-.15,2.5),(5.5,3.9,.13),M['stone']);roof.rotation_euler[0]=.11
    for i in range(8):
        for j in range(5):box('Honed terrace paver',(-3.1+i*1.1,-2.9-j*.74,.016),(1.085,.725,.065),M['stone'],.004)
    box('Terrace riser',(1,-6.08,-.065),(9.1,.15,.19),M['stone'])
    box('Gravel rain garden',(0,-7.1,-.04),(14,1.25,.09),M['soil'])
    for i in range(22):grasses(-6.5+i*.6,-6.9+random.uniform(-.4,.2),100+i)
    for i,(x,y,h) in enumerate([(-6,1,4.3),(-7,-2,3.8),(6,5,4.7),(7,-.5,4.1),(-8,8,6),(3,10,5.5),(9,9,5)]):tree(x,y,h,310+i)
    for i in range(90):
        box('Garden boundary cedar slat',(-10+i*.23,8.5,.73),(.215,.055,1.55),M['walnut'],.004)
    for i in range(12):grasses(-6.2+i*.23,-2.8+random.uniform(-.6,.5),420+i)
    for i in range(15):grasses(5.2+random.uniform(-.4,.6),-2.6+i*.3,460+i)
    if after:
        for x in [-3.9,5.1]:
            cylinder('Terrace lamp',(x,-4.95,.29),.048,.51,M['dark'])
            cylinder('Terrace diffuser',(x,-4.95,.5),.041,.06,M['opal'])
    return camera((10.1 if args.project==1 else 8.3,-12.8,3.15),(0,-.15,1.55),35)


def organize_scene():
    groups={name:bpy.data.collections.new(name) for name in ['01 Architecture','02 Furniture and fittings','03 Landscape','04 Cameras and light']}
    for c in groups.values():bpy.context.scene.collection.children.link(c)
    for o in list(bpy.context.scene.objects):
        name=o.name.lower()
        if o.type in ['LIGHT','CAMERA']:key='04 Cameras and light'
        elif any(word in name for word in ['leaf','tree','branch','grass','meadow','soil','garden','site datum']):key='03 Landscape'
        elif any(word in name for word in ['wall','floor','facade','roof','soffit','beam','ceiling','glazing','glass pane','mullion','gable','window','terrace','gutter','downpipe','slab','threshold']):key='01 Architecture'
        else:key='02 Furniture and fittings'
        for c in list(o.users_collection):c.objects.unlink(o)
        groups[key].objects.link(o)


def render_file(name):
    path=Path(args.output)/name; bpy.context.scene.render.filepath=str(path)
    bpy.ops.render.render(write_still=True)
    return {'file':name, 'bytes':path.stat().st_size, 'sha256':hashlib.sha256(path.read_bytes()).hexdigest()}


def main():
    Path(args.output).mkdir(parents=True,exist_ok=True)
    states=['after','before'] if args.state=='both' else [args.state]
    meta={'revision':2,'project':NAMES[args.project],'type':'Original fictional architectural visualisation','renderer':'Blender Cycles','blender':bpy.app.version_string,'unit':'metres','seed':417+args.project,'samples':args.samples,'resolution':[args.width,round(args.width*2/3)],'states':{},'renders':[]}
    for state in states:
        random.seed(417+args.project);setup()
        cam=(exterior if args.project in [1,4] else living if args.project in [2,5] else kitchen)(state=='after')
        organize_scene()
        meta['states'][state]=cam
        meta['renders'].append(render_file(NAMES[args.project]+'-'+state+'.jpg'))
        if state=='after':
            meta['scene']={'objects':len(bpy.context.scene.objects),'mesh_objects':sum(o.type=='MESH' for o in bpy.context.scene.objects),'materials':len(bpy.data.materials)}
            if args.blend_dir:
                Path(args.blend_dir).mkdir(parents=True,exist_ok=True)
                bpy.ops.wm.save_as_mainfile(filepath=str((Path(args.blend_dir)/(NAMES[args.project]+'.blend')).resolve()),compress=True)
            if args.details and args.project==0:
                for name,loc,target,lens in [('oak',(-.85,-2.5,1.3),(.2,.2,.62),50),('limestone',(1.8,-1.6,1.65),(.65,.35,.98),55),('limewash',(-.75,-.4,1.83),(-.5,3.2,1.65),48)]:
                    camera(loc,target,lens);meta['renders'].append(render_file('material-'+name+'.jpg'))
    if args.state=='both':assert meta['states']['before']==meta['states']['after'],'Paired cameras must be identical'
    (Path(args.output)/(NAMES[args.project]+'-provenance.json')).write_text(json.dumps(meta,indent=2)+'\n')
    print('NORTHLINE_STUDIO_PASS '+json.dumps({'project':meta['project'],'states':states,'renders':len(meta['renders'])}))


if __name__=='__main__':main()
