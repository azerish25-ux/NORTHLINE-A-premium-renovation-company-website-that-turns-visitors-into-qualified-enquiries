"""Original NORTHLINE architectural visualisations; run with Blender 4.x.
Paired states share the same shell, lens and camera. No third-party photos.
blender -b -t 4 -P tools/render_architecture.py -- --project 0 --output assets
"""
import bpy, math, random, argparse, os, json, sys
from mathutils import Vector
parser = argparse.ArgumentParser()
parser.add_argument('--project', type=int, choices=range(6), default=0)
parser.add_argument('--output', default='theme/northline/assets/images')
parser.add_argument('--samples', type=int, default=96)
args = parser.parse_args(sys.argv[sys.argv.index('--')+1:])
NAMES = ['birch-house','tidal-house','alder-house','orchard-house','courtyard-house','harbour-house']

def plain(name, color, rough=.5, metal=0, transmission=0, emission=0):
    m=bpy.data.materials.new(name); m.diffuse_color=(*color,1); m.use_nodes=True
    p=m.node_tree.nodes.get('Principled BSDF'); p.inputs['Base Color'].default_value=(*color,1)
    p.inputs['Roughness'].default_value=rough; p.inputs['Metallic'].default_value=metal
    p.inputs['Transmission Weight'].default_value=transmission
    if emission:
        p.inputs['Emission Color'].default_value=(*color,1); p.inputs['Emission Strength'].default_value=emission
    return m

def textured(name,a,b,scale,stretch=(1,1,1),rough=.55,bump=.06):
    m=plain(name,a,rough); n=m.node_tree.nodes; l=m.node_tree.links; p=n.get('Principled BSDF')
    coord=n.new('ShaderNodeTexCoord'); mapping=n.new('ShaderNodeVectorMath'); mapping.operation='MULTIPLY'; mapping.inputs[1].default_value=stretch
    l.new(coord.outputs['Generated'],mapping.inputs[0]); noise=n.new('ShaderNodeTexNoise'); noise.inputs['Scale'].default_value=scale; noise.inputs['Detail'].default_value=3; noise.inputs['Roughness'].default_value=.7; l.new(mapping.outputs['Vector'],noise.inputs['Vector'])
    ramp=n.new('ShaderNodeValToRGB'); ramp.color_ramp.elements[0].position=.18; ramp.color_ramp.elements[0].color=(*a,1); ramp.color_ramp.elements[1].position=.82; ramp.color_ramp.elements[1].color=(*b,1)
    l.new(noise.outputs['Fac'],ramp.inputs[0]); l.new(ramp.outputs['Color'],p.inputs['Base Color'])
    bn=n.new('ShaderNodeBump'); bn.inputs['Strength'].default_value=bump; bn.inputs['Distance'].default_value=.015; l.new(noise.outputs['Fac'],bn.inputs['Height']); l.new(bn.outputs['Normal'],p.inputs['Normal'])
    return m

def cube(name,loc,dims,mat,bevel=.008):
    bpy.ops.mesh.primitive_cube_add(size=1,location=loc); o=bpy.context.object; o.name=name; o.dimensions=dims; bpy.ops.object.transform_apply(location=False,rotation=False,scale=True)
    if mat:o.data.materials.append(mat)
    if bevel:
        mod=o.modifiers.new('Light-catching arris','BEVEL'); mod.width=bevel; mod.segments=3
        o.modifiers.new('Weighted normals','WEIGHTED_NORMAL')
    return o

def cyl(name,loc,r,depth,mat,vertices=40):
    bpy.ops.mesh.primitive_cylinder_add(vertices=vertices,radius=r,depth=depth,location=loc); o=bpy.context.object; o.name=name; o.data.materials.append(mat)
    b=o.modifiers.new('Soft arris','BEVEL'); b.width=min(r*.12,.016); b.segments=3
    o.modifiers.new('Weighted normals','WEIGHTED_NORMAL')
    return o

def sphere(name,loc,scale,mat):
    bpy.ops.mesh.primitive_uv_sphere_add(segments=24,ring_count=12,radius=1,location=loc); o=bpy.context.object; o.name=name; o.scale=scale; o.data.materials.append(mat)
    for f in o.data.polygons:f.use_smooth=True
    return o

def curve(name,points,r,mat):
    c=bpy.data.curves.new(name,'CURVE'); c.dimensions='3D'; c.bevel_depth=r; c.bevel_resolution=3
    s=c.splines.new('BEZIER'); s.bezier_points.add(len(points)-1)
    for p,co in zip(s.bezier_points,points):p.co=co; p.handle_left_type='AUTO'; p.handle_right_type='AUTO'
    o=bpy.data.objects.new(name,c); bpy.context.collection.objects.link(o); o.data.materials.append(mat); return o

def camera(loc,target,lens=32):
    bpy.ops.object.camera_add(location=loc); c=bpy.context.object; c.rotation_euler=(Vector(target)-c.location).to_track_quat('-Z','Y').to_euler(); c.data.lens=lens; bpy.context.scene.camera=c; c.data.clip_end=200
    return {'location':list(loc),'target':list(target),'lens_mm':lens}

def area(name,loc,target,power,size,color=(1,.92,.8)):
    bpy.ops.object.light_add(type='AREA',location=loc); o=bpy.context.object; o.name=name; o.data.energy=power; o.data.shape='DISK'; o.data.size=size; o.data.color=color; o.rotation_euler=(Vector(target)-o.location).to_track_quat('-Z','Y').to_euler()

def setup():
    bpy.ops.object.select_all(action='SELECT'); bpy.ops.object.delete(use_global=False)
    s=bpy.context.scene; s.render.engine='CYCLES'; s.cycles.samples=args.samples; s.cycles.use_denoising=True; s.cycles.adaptive_threshold=.035; s.cycles.max_bounces=8
    s.render.resolution_x=1920; s.render.resolution_y=1280; s.render.resolution_percentage=100
    s.render.image_settings.file_format='JPEG'; s.render.image_settings.quality=94
    s.view_settings.view_transform='AgX'; s.view_settings.look='AgX - Medium High Contrast'; s.render.film_transparent=False
    w=bpy.data.worlds.new('Atlantic daylight'); w.use_nodes=True; s.world=w; n=w.node_tree.nodes; l=w.node_tree.links
    sky=n.new('ShaderNodeTexSky'); sky.sky_type='NISHITA'; sky.sun_elevation=math.radians(28); sky.sun_rotation=math.radians(135); sky.altitude=100; sky.air_density=1.15; sky.dust_density=1.8
    l.new(sky.outputs[0],n.get('Background').inputs[0]); n.get('Background').inputs[1].default_value=.3
    bpy.ops.object.light_add(type='SUN',location=(4,-5,7)); sun=bpy.context.object; sun.data.energy=1.8; sun.data.angle=.06; sun.rotation_euler=(.6,-.5,-.65)
    global plaster,stone,wood,dark,brass,linen,green,glass,soil,leaf,white
    plaster=textured('Warm limewash',(.62,.59,.51),(.83,.8,.72),85,bump=.12,rough=.91)
    stone=textured('Honed limestone',(.5,.47,.38),(.74,.72,.64),22,stretch=(1,1,4),bump=.09,rough=.38)
    wood=textured('Quarter-sawn oak',(.21,.115,.05),(.52,.34,.17),4,stretch=(10,1,80),bump=.17,rough=.36)
    dark=plain('Charcoal steel',(.028,.034,.028),.33,.75)
    brass=plain('Brushed aged brass',(.4,.27,.11),.27,.78)
    linen=textured('Natural woven linen',(.49,.46,.37),(.76,.73,.64),160,bump=.32,rough=.95)
    green=plain('Grey olive lacquer',(.17,.22,.15),.43)
    glass=plain('Low iron glazing',(.94,.98,1),.06,transmission=1)
    soil=textured('Meadow ground',(.065,.083,.032),(.24,.26,.1),90,bump=.6,rough=1)
    leaf=plain('Olive leaves',(.16,.23,.08),.6)
    white=plain('Bone ceramic',(.86,.83,.75),.22)
    if args.project==5:
        linen=textured('Slate linen',(.14,.18,.19),(.29,.33,.33),150,bump=.32,rough=.95)
    if args.project==4:
        wood=textured('Cedar joinery',(.14,.065,.026),(.37,.21,.095),4,stretch=(10,1,80),bump=.17,rough=.42)

def plant(x,y,z=0,height=1.8,seed=0):
    rng=random.Random(seed+55); cyl('Stone planter',(x,y,z+.22),.23,.44,stone)
    curve('Olive trunk',[(x,y,z+.2),(x+.04,y,z+height*.55),(x-.05,y+.02,z+height)],.023,wood)
    for j in range(9):
        h=z+height*(.4+j*.065); ang=j*2.39; dx=math.cos(ang)*.45; dy=math.sin(ang)*.45
        curve('Branch',[(x,y,h),(x+dx*.5,y+dy*.5,h+.13),(x+dx,y+dy,h+.27)],.007,wood)
        for k in range(9):
            t=(k+1)/10; side=(-1)**k; cx=x+dx*t+math.sin(ang)*.07*side; cy=y+dy*t-math.cos(ang)*.07*side
            o=sphere('Lanceolate leaf',(cx,cy,h+.27*t),(.016,.075,.006),leaf); o.rotation_euler=(rng.uniform(-.5,.5),.3,ang)

def window(x,y,z,w,h,orientation='back'):
    if orientation=='back':
        cube('Glazing',(x,y,z),(w,.025,h),glass,0)
        for dx in (-w/2,0,w/2):cube('Mullion',(x+dx,y-.025,z),(.035,.075,h+.07),dark)
        for dz in (-h/2,h/2):cube('Transom',(x,y,z+dz),(w+.07,.075,.035),dark)
    else:
        cube('Glazing',(x,y,z),(.025,w,h),glass,0)
        for dy in (-w/2,0,w/2):cube('Mullion',(x-.025,y+dy,z),(.075,.035,h+.07),dark)
        for dz in (-h/2,h/2):cube('Transom',(x,y,z+dz),(.075,w+.07,.035),dark)

def floor(after=True,w=7,d=7):
    cube('Subfloor',(0,0,-.085),(w,d,.16),dark,0)
    if after:
        for ix in range(int(w/.2)):
            for iy in range(4):cube('Oak floorboard',(-w/2+.1+ix*.2,-d/2+(iy+.5)*d/4,0),(.197,d/4-.006,.055),wood,.0015)
    else:
        old=textured('Original ceramic tiles',(.24,.19,.13),(.48,.39,.27),4,rough=.58)
        for i in range(int(w/.5)):
            for j in range(int(d/.5)):cube('Existing tile',(-w/2+.25+i*.5,-d/2+.25+j*.5,0),(.49,.49,.05),old,.001)

def room_shell(after=True):
    floor(after)
    cube('Original left wall',(-3.5,0,1.8),(.18,7,3.6),plaster)
    cube('Back wall left pier',(-2.1,3.5,1.8),(2.8,.18,3.6),plaster)
    cube('Back wall right pier',(3.25,3.5,1.8),(.5,.18,3.6),plaster)
    cube('Back wall lintel',(1.15,3.5,3.35),(3.7,.18,.5),plaster)
    window(1.15,3.48,1.58,3.7,3.05)
    cube('Right parapet',(3.5,0,.38),(.18,7,.76),plaster)
    cube('Right lintel',(3.5,0,3.35),(.18,7,.5),plaster)
    window(3.48,0,1.94,7,2.3,'side')
    cube('Ceiling',(0,0,3.65),(7.2,7.2,.14),plaster)
    if after:
        for y in (-2,0,2):cube('Oak ceiling beam',(0,y,3.45),(7.1,.12,.28),wood)
    cube('Exterior lawn',(2,8,-.12),(20,14,.1),soil,0)
    for i in range(7):plant(-6+i*2,8+random.uniform(-1,2),-.1,random.uniform(2.2,3.5),i)
    area('Window daylight',(2,3.1,2.6),(0,-1,.5),480,3,(.8,.88,1))
    area('Camera-side soft fill',(-1,-4,2.8),(0,1,1),150,4)

def cabinet(x,y,w=.6,after=True):
    mat=(green if args.project==3 else wood) if after else plain('Original maple laminate',(.28,.13,.045),.48)
    cube('Cabinet box',(x,y,.49),(w,.63,.83),dark)
    cube('Recessed plinth',(x,y,.095),(w,.49,.15),dark)
    for z,h in ((.32,.4),(.69,.29)):
        cube('Joinery front',(x,y-.331,z),(w-.008,.027,h),mat,.003)
        if after:cube('Finger pull',(x,y-.35,z+h/2-.018),(w*.65,.01,.018),brass,.002)
        else:curve('Original bar handle',[(x-.12,y-.37,z+.08),(x+.12,y-.37,z+.08)],.007,brass)

def stool(x,y):
    cyl('Oak counter stool seat',(x,y,.66),.235,.075,wood)
    for dx in (-.14,.14):
        for dy in (-.12,.12):curve('Splayed stool leg',[(x+dx*1.4,y+dy*1.4,.04),(x+dx,y+dy,.63)],.021,wood)
    curve('Foot rail',[(x-.18,y-.17,.25),(x+.18,y-.17,.25)],.012,dark)
    curve('Bentwood back',[(x-.21,y-.12,.68),(x-.23,y+.13,.99),(x+.23,y+.13,.99),(x+.21,y-.12,.68)],.023,wood)

def kitchen(after=True):
    room_shell(after)
    for i in range(7):cabinet(-2.9+i*.64,2.95,.64,after)
    cube('Stone counter',(-.96,2.91,.952),(4.7,.77,.065),stone if after else white)
    cube('Slab backsplash',(-.96,3.38,1.23),(4.7,.025,.52),stone)
    for x in (-2.75,-2.05):
        cube('Pantry carcass',(x,2.95,1.8),(.69,.62,1.77),dark)
        cube('Pantry door',(x,2.605,1.78),(.677,.037,1.74),wood)
        cube('Brass pull',(x+.22,2.57,1.62),(.018,.035,.44),brass)
    cube('Integrated oven',(-1.27,2.57,1.34),(.59,.025,.53),dark)
    cube('Oven glass',(-1.27,2.55,1.32),(.5,.012,.33),plain('Tinted oven glass',(.015,.02,.021),.13,.35))
    curve('Oven handle',[(-1.5,2.52,1.54),(-1.04,2.52,1.54)],.012,brass)
    if after:
        cube('Floating shelf',(.4,3.19,2.05),(2.3,.31,.048),wood)
        for x in (-.45,-.23,1.1):cyl('Cup',(x,3.13,2.14),.065,.14,white)
    ix,iy=(0,.5) if after else (-.15,.55); length=2.65 if after else 1.65
    cube('Island carcass',(ix,iy,.48),(length,1.04,.89),green if args.project==3 and after else wood)
    for x in ((-.93,-.31,.31,.93) if after else (-.4,.4)):cube('Island door reveal',(ix+x,iy-.537,.51),(.012,.012,.73),dark,.001)
    # Four slabs leave a real opening for the undermount sink.
    cube('Island front stone',(ix,iy-.42,.99),(length+.12,.3,.085),stone)
    cube('Island rear stone',(ix,iy+.34,.99),(length+.12,.46,.085),stone)
    cube('Island left stone',(ix-length/4-.17,iy-.05,.99),(length/2-.28,.46,.085),stone)
    cube('Island right stone',(ix+length/4+.17,iy-.05,.99),(length/2-.28,.46,.085),stone)
    cube('Sink basin',(ix,iy-.06,.8),(.59,.45,.025),dark)
    for dx in (-.31,.31):cube('Sink side',(ix+dx,iy-.06,.89),(.015,.45,.2),dark)
    for dy in (-.28,.16):cube('Sink rim',(ix,iy+dy,.89),(.61,.015,.2),dark)
    curve('Brass mixer',[(ix+.37,iy+.14,1.02),(ix+.37,iy+.14,1.37),(ix+.2,iy+.14,1.44),(ix+.11,iy+.06,1.34)],.017,brass)
    for x in ((-.8,0,.8) if after else (0,)):stool(x,iy-1.07)
    for x in (-.85,.8):
        curve('Pendant suspension',[(x,iy,3.47),(x,iy,2.34)],.005,dark)
        sphere('Opal pendant',(x,iy,2.25),(.18,.18,.16),plain('Opal glow',(.96,.88,.7),.22,emission=.7))
    cyl('Unglazed vase',(1.16,2.97,1.17),.12,.38,stone)
    for i in range(5):curve('Dried stems',[(1.16,2.97,1.23),(1.1+i*.027,2.94,1.75+i*.025)],.002,wood)
    cube('Bread board',(-1.05,2.92,1.004),(.47,.32,.025),wood)
    sphere('Sourdough loaf',(-1.05,2.92,1.08),(.16,.11,.08),stone)
    plant(-2.8,.2,height=1.95)
    return camera((-2.8,-6.8,2.15),(0,1.3,1.3),31)

def living(after=True):
    room_shell(after)
    rug=textured('Wool rug',(.35,.33,.28),(.6,.57,.49),190,bump=.7,rough=1)
    cube('Hand-knotted rug',(.2,.1,.05),(3.95,3.7,.018),rug,.01)
    if not after:cube('Removed partition',(-1.45,2.15,1.52),(3.8,.15,3.04),plaster)
    sofa=linen if after else plain('Original brown upholstery',(.1,.055,.022),.9)
    cube('Low sofa base',(-1.38,.55,.26),(1,2.5,.28),wood,.06)
    cube('Sofa back',(-1.73,.55,.71),(.3,2.48,.83),sofa,.11)
    for y in (-.29,.53,1.35):
        cube('Seat cushion',(-1.32,y,.47),(.92,.78,.26),sofa,.095)
        pillow=cube('Lumbar cushion',(-1.48,y,.79),(.22,.64,.48),sofa,.13); pillow.rotation_euler[1]=-.15
    for y in (-.82,1.88):cube('Sofa arm',(-1.35,y,.64),(1,.17,.65),sofa,.065)
    cube('Limestone low table',(.05,.42,.34),(1.2,1.44,.065),stone,.015)
    for x in (-.34,.34):cube('Table slab support',(x,.42,.18),(.095,1.17,.3),stone,.015)
    for i in range(3):cube('Art book',(0,.26,.393+i*.034),(.36,.46,.03),white,.001)
    cyl('Ceramic vessel',(.35,.73,.5),.105,.24,white)
    for x,y in ((1.6,-.65),(1.7,1.3)):
        for dx in (-.28,.28):
            for dy in (-.25,.25):cube('Chair leg',(x+dx,y+dy,.26),(.042,.042,.48),wood)
        cube('Chair seat',(x,y,.47),(.66,.7,.18),linen,.08)
        back=cube('Oak chair back',(x+.27,y,.74),(.07,.71,.61),wood,.025); back.rotation_euler[1]=.14
        cube('Chair back pad',(x+.22,y,.78),(.14,.62,.43),linen,.065)
    cube('Limewash chimney breast',(-3.19,.5,1.62),(.56,2.55,3.24),plaster)
    cube('Inset fireplace',(-2.89,.5,.56),(.016,1.1,.66),dark)
    cube('Hearth',(-2.7,.5,.13),(.9,1.65,.13),stone)
    for i in range(5):curve('Hearth logs',[(-2.84,.2+i*.13,.3),(-2.66,.2+i*.13,.38)],.045,wood)
    cube('Oak art frame',(-2.886,.5,2.05),(.05,1.02,1.22),wood)
    cube('Linen artwork',(-2.852,.5,2.05),(.017,.93,1.13),linen,0)
    plant(2.4,2.7,height=2.15,seed=90)
    curve('Floor lamp',[(1.8,2.7,.05),(1.8,2.7,1.65)],.012,brass); cyl('Lamp base',(1.8,2.7,.05),.17,.03,brass)
    bpy.ops.mesh.primitive_cone_add(vertices=64,radius1=.24,radius2=.14,depth=.33,location=(1.8,2.7,1.66)); bpy.context.object.data.materials.append(linen)
    return camera(((1.4 if args.project==5 else -1.2),-7,2.18),(0,.65,1.25),31)

def exterior(after=True):
    cube('Earth',(0,0,-.18),(32,30,.3),soil,0)
    cube('Existing house',(-2,4,1.7),(7,4,3.4),plaster)
    for side in (-1,1):
        r=cube('Standing seam roof',(-2,4+side*1.12,4.04),(7.4,2.55,.16),dark); r.rotation_euler[0]=side*math.radians(29)
        for i in range(30):
            rr=cube('Roof seam',(-5.6+i*.25,4+side*1.12,4.135),(.021,2.55,.035),dark); rr.rotation_euler[0]=side*math.radians(29)
    for x in (-4.2,-1.8,.55):window(x,1.98,2.1,1.3,1.6)
    if after:
        cube('Extension roof',(1,-.15,2.96),(6.1,4.5,.2),dark)
        cube('Extension slab',(1,-.15,.13),(6.1,4.5,.2),stone)
        window(1,-2.38,1.56,6,2.7); window(4.02,-.15,1.56,4.4,2.7,'side')
        cube('Cedar extension wall',(-2.02,-.15,1.5),(.16,4.5,2.8),wood)
        for y in [i*.12-2.3 for i in range(37)]:cube('Cedar battens',(-2.12,y,1.56),(.06,.042,2.72),wood,.003)
        cube('Dining table',(1,-.25,.82),(2.1,.95,.095),wood)
        for x in (.2,1.8):
            for y in (-.53,.03):cube('Table leg',(x,y,.43),(.075,.075,.8),wood)
        for x in (.25,1,1.75):
            for y in (-1.1,.67):stool(x,y)
        for x in (.15,1.8):
            curve('Pendant wire',[(x,-.25,2.84),(x,-.25,2.14)],.005,dark)
            sphere('Warm pendant',(x,-.25,2.04),(.17,.17,.14),plain('Pendant luminance',(.96,.85,.63),.4,emission=2))
        area('Interior warm pool',(1,-.1,2.7),(1,-.1,.1),170,2)
    else:
        cube('Old conservatory base',(1,-.15,.34),(5.3,3.7,.5),plaster)
        window(1,-2.04,1.49,5.3,1.8); window(3.68,-.15,1.49,3.7,1.8,'side')
        roof=cube('Original opaque roof',(1,-.15,2.56),(5.4,3.9,.14),white); roof.rotation_euler[0]=.11
    for i in range(6):
        for j in range(4):cube('Terrace paver',(-2.5+i*1.22,-2.9-j*.8,.01),(1.2,.78,.07),stone,.003)
    for i in range(10):
        x=-6+i*1.35; y=-6.6+random.uniform(-.3,.3)
        for j in range(12):
            dx=random.uniform(-.3,.3); dy=random.uniform(-.2,.2)
            curve('Ornamental grass',[(x+dx,y+dy,0),(x+dx*1.7,y+dy*1.7,.5+random.random()*.4)],.004,leaf)
    for x,y,h in ((-6,2,4.5),(-7,-1,3.5),(6,4,4.4),(7,-1,3.9)):
        plant(x,y,height=h,seed=int(h*10))
        for k in range(28):
            ang=k*2.399; radius=1.3*math.sqrt(k/28); sphere('Tree canopy',(x+math.cos(ang)*radius,y+math.sin(ang)*radius,h-.2+random.random()*.9),(.4,.5,.46),leaf)
    return camera(((8.8 if args.project==4 else 10.1),-12.6,(3.4 if args.project==4 else 4.2)),(0,-.1,1.5),36)

os.makedirs(args.output,exist_ok=True)
meta={'project':NAMES[args.project],'type':'Original fictional architectural visualisation','renderer':'Blender Cycles','states':{}}
for after in (True,False):
    random.seed(417+args.project); setup()
    cam=exterior(after) if args.project in (1,4) else living(after) if args.project in (2,5) else kitchen(after)
    state='after' if after else 'before'; meta['states'][state]=cam
    bpy.context.scene.render.filepath=os.path.join(args.output,f'{NAMES[args.project]}-{state}.jpg'); bpy.ops.render.render(write_still=True)
assert meta['states']['before']==meta['states']['after'],'Paired cameras must be identical'
with open(os.path.join(args.output,NAMES[args.project]+'-provenance.json'),'w') as f:json.dump(meta,f,indent=2)
