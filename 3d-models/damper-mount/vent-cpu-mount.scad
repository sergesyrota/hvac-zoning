/*
This box generator is taken from here: http://www.thingiverse.com/thing:66030
You need to have it in your library folder to be able to generate the enclosure properly.

@brief Generic Electronic Device Packaging for Tyndall version 2
@details This OpenSCAD script will generate a 2 part fit together packaging.

This starts with the user entering some basic values:
	1. Dimensions of the object to be packaged in XYZ (device_xyz, [x,y,z])
	2. Size of the gap between each side of the object and the internal wall of the packaging (clearance_xyz, [x,y,z])
	3. How thick the material of the packaging is (wall_t)
	4. The external radius of the rounded corners of the packaging (corner_radius)
	5. How many sides do these rounded corners have? (corner_sides, 3 to 10 are good for most items, 1 will give you chamfered edges)
	6. How high is the lip that connects the 2 halves? (lip_h, 1.5 to 3 are good for most applications)
	7. How tall is the top relative to the bottom? (top_bottom_ratio, 0.1 to 0.9, 0.5 will give you 2 halves of equal height)
	8. Does the part have mouse ears or not? (has_mouseears, true or false)
	9. How thick the flat discs that make the mouse ears should be (mouse_ear_thickness, twice print layer thickness is a good idea)
	10. How large the flat discs that make the mouse ears are (mouse_ear_radius, 5 to 15 generally work well)
	11. What parts you want and how they are laid out (layout, [beside, stacked, top, bottom])
	12. How far apart the 2 halves are in the "beside" layout (separation, 2 is good)
	13. How much of an overlap (-ve) or gap (+ve) is there between the inner and outer lip surfaces (lip_fit, a value of 0 implies they meet perfectly in the middle, this will depend on your material printer etc. so you will likely need to play around with this variable)

Next the user can modify the data structures that create holes, posts and text on each face of the packaging. These are complicated and may require you to play around with values or read some of the comments further down in the code
	1. The cutouts and depressions used on the packaging (holes=[[]])
		format for each hole [face_name, shape_name, shape_position[x_pos,y_pos,x_offs,y_offs,rotate,align], shape_size[depth,,,]]
	2. The internal supporting structures used on the packaging (posts=[[]])
		format for each post [face_name, shape_name shape_position[x_pos,y_pos,x_offs,y_offs,rotate,align], shape_size[depth,,,]]
	3. The engraved text used on the packaging (text=[[]])
		format for each piece of text [face_name, text_to_write, shape_position[x_pos,y_pos,x_offs,y_offs,rotate,align], shape_size[depth,font_height,font_spacing,mirror]] Note: for silly reasons mirror must be 0 or 1 corresponding to false and true in this version
	
	Which of the 6 sides of the packaging do you want this feature on? (face_name, T,B,N,E,W,S)
		"T", The Top or Z+ face
		"B", The Bottom or Z- face
		"N", the North or Y+ face
		"E", the East or X+ face
		"W", the West or X- face
		"S", the South or Y- Face
	Where on the face do you want this feature (shape_position [x_pos,y_pos,x_offs,y_offs,rotate,align] )
		x_pos, how far along the face do you move in X
		y_pos, how far along the face do you move in Y
		x_offs, where along the face do you take measurements from in X
		y_offs, where along the face do you take measurements from in Y
		rotate, how much do you want the object rotated in degrees
			if you do not use any of the above please set them to 0! do not just leave them empty!
		align, do you want the object aligned with the "inside" or the "outside" of the packaging face
		
	What shape do you want? (shape_name, Cone/Ellipse/Cylinder/Round_Rect/Square/Rectangle/Nub_Post/Dip_Post/Hollow_Cylinder)
	What are the shape's dimensions (shape_size[depth,,,])...you will need to read the section below as they are different for each shape
		"Square" shape_size[depth, length_breadth]
		"Rectangle" shape_size[depth, length, breadth]
		"Round_Rect" shape_size[depth, length, breadth, corner_radius, corner_sides]
		"Cylinder" shape_size[depth, radius ,sides]
		"Ellipse" shape_size[depth, radius_length, radius_breadth, sides]
		"Cone" shape_size[depth, radius_bottom, radius_top ,sides]
		"Nub_Post" shape_size[depth, radius_bottom, radius_top, depth_nub, sides]
		"Dip_Post" shape_size[depth, radius_bottom, radius_top, depth_dip, sides]
		"Hollow_Cylinder" shape_size[depth, radius_outside, radius_inside ,sides]
	A string of text you want to have carved into a face (text_to_write)
		for text shape_size[depth,font_height,font_spacing,mirror]

Once the user has provided this data the shape is made as follows:
	1. Calculate external size of packaging based on "device_xyz", "clearance_xyz" & "wall_t"
	2. Create Hollow cuboidal shape by differencing a slightly smaller one from a slightly larger one
	3. From "posts" create internal support structures and union that with the previous shape
	4. From "holes" create shapes on each face and difference that from the previous shape
	5. From "text" create letters on each face and difference that from the previous shape
	6. Using the packaging height, "lip_h", "top_bottom_ratio" & "lip_fit" split teh previous shape into a top and bottom half
	7. Using layout & separation arrange the parts as specified by the user
	8. Using layout, mouse_ears, mouse_ear_thickness & mouse_ear_radius union the mouse ears to the previous shapes correctly
	9. Done!



Author Mark Gaffney
Version 2.6k
Date 2013-07-09

@ToDo:
fix internal radii no-of-sides when using chamfered package to maintain wall thickness

Warning:
	Some combinations of values, shapes and locations etc. may lead to an invalid or non-manifold shape beign created, preventing you from exporting a .stl
	If this happens try to remove or change some of the features or values until you get a valid shape and then add them back gradually until you find the offending item, then change it so it doesn't cause a problem
	
	Note: This thing uses  HarlanDMii's"write.scad" module http://www.thingiverse.com/thing:16193
	which you will need to have downloaded and located in the directory if you want to run this on your PC
	
	Generating text can be very slow!
	
	When generating a stacked layout it may look like the top is taller than it should be, this seems to be a visualisation bug in OpenSCAD, if you create a .STL it will be perfect

@note
WIMUv4 variant 2013
	This variant is used to make a production packaging for WIMUv3a

Changes from previous versions:
v2.6k
	moved and enlarged uUSB & switch holes to prevent fragile structures being formed on advice of i.materialise
	moved lables for switch, uUSB and uSD from top to sides of object
v2.6j
	added text for indicating switch on/off
	added holes and guides for internal reset/bootloader-enable switches
	added a hole for screw in screwpost
v2.6i
	packaging for WIMUv4 2013 version with small battery (same size as WIMUv3a and WIMUv3
	began to fix a bug where interlocking features didn't generate in the correct location if the top_bottom_ratio wasn't 0.5
		only implemented and tested for spheroids
v2.6h
 - 	added option to choose style of interlocking features
 - 		added new style of interlocking  features(overlapping spheroid)
 - 	Changed depth of uUSB overmold cutout to remove unnecessary features
 - 	added cutout beneath uUSB to enahnce visibility of SMT LEDs
 - 	Added option to flip the halves if only makigna top or bottom

v2.6g
 - 	changed style of interlocking cutouts (overlapping flattened hemi-hexagonal prisms)
 - 	added interlocking feature on side with many cutouts
 - 	added support for controlling the r1 and r2 and height of the interlocking features (note: the side with cutouts has hardcoded length of 19)
 - 	moved switch cutout down 0.5mm
 - 	rotate post above zigbee from 30 to 0
 - 	remove post_tolerance from "PCB lateral retainers" as fit was very loose on prototypes and they can be easily pared down with a file etc.
v2.6f
 - 	modified tolerances from 0.2-0.35 on advice from i.materialise as minimum tolerance is 0.3mm
 - 	added simple interlocking feature on centre of lips (slightly interlocking hemispherical tongue & groove)
 - 	made corners more rounded (moved from 3 facets to 9)
v2.6e
 - 	modified text especially size and depth for i.materialise order 0.4-0.5mm wide and deep
 - 	ensure model is output as 2 separate stl files with 1 part each!
v2.6d
 - 	Implemented flanges on bottom half based on belt_holder_v3.1.scad
v2.6c
 - 	Implemented ability to choose box shape i.e. "cuboid","rounded4sides", "rounded6sides", "chamfered6sides"
 - 	Ensured "box_type" module is used in generation of each box_half
v2.6b
 - 	investigated and fixed some top lip mis alignment
 - 	added wimuv3 stack as imported .stl
v2.6
 - 	implements loading external objects (such as .stl files) to place on faces
 - 	implemented overlooked translation of text on faces from previous version
 - 	fixed box_t in make_cutouts
 - 	Note: Top lip seems to be malformed lip_h is 0.5 less than it should be
v2.5 (AKA cutouts_on_faces_for_loop-v0_2.scad)
 - 	Implement tolerancing of connection between 2 halves using lip_fit
 - 	implement text on faces
 - 	Complete documentation
v2.4 (AKA cutouts_on_faces_for_loop-v0_4.scad)
 - 	user provides device dimensions and internal clearance(s), packaging dimensions are calculated based on this
 - 	preparations to allow for different wall_t in x,y,z
 - 	implement posts
 - 	mouse ears work properly
 - 	2 halves in "beside" layout joined by union

v2.3 2013-03-23 (AKA cutouts_on_faces_for_loop-v0_3.scad)
 - 	fixed calls to make_box and half_box modules
 - 	added ability to handle "holes" array for making cutouts to make_box and half_box modules
 - 	added module for rounded cuboid
 - 	fixed errors on rounded cuboid cutouts's use of a_bit
 - 	fixed translate errors for making lips on box
 - 	ensured all parts generate on z=0
 - 	gave top (greenish) and bottom (reddish) different colours for ease of identification
 - 	rotated top in "beside" layout to more intuitive orientation
 - 	added box cross module and included in hull calculations to ensure box sizes are exact even with low side count
 - 	added box_type module to allow choice of different shaped boxes or automatically swap them based on the current variables
 - 	added mouse_ears module
 Note: In stacked mode you may notice the red half looks like it is the full length, this appears to be a visualisation bug in openscad, the part is generated correctly

v2.2 2013-03-22 (AKA cutouts_on_faces_for_loop-v0_2.scad)
based on My own structure
 - 	New format [face_name, shape_name, shape_position[x_pos,y_pos,x_offs,y_offs,rotate,align], shape_size[depth,,,]]
 - 	 - 	face_name ("N", "S", "E", "W", "T", "B")
 - 	 - 	shape_name ("Square", "Rectangle" , "Round_Rect", "Cylinder", "Ellipse" , "Cone")
 - 	 - 	shape_position[x_pos,y_pos,x_offs,y_offs,rotate,align=("inside" or "outside")]
 - 	 - 	 - 	"Square" shape_size[depth, length_breadth]
 - 	 - 	 - 	"Rectangle" shape_size[depth, length, breadth]
 - 	 - 	 - 	"Round_Rect" shape_size[depth, length, breadth, corner_radius, corner_sides]
 - 	 - 	 - 	"Cylinder" shape_size[depth, radius ,sides]
 - 	 - 	 - 	"Ellipse" shape_size[depth, radius_length, radius_breadth, sides]
 - 	 - 	 - 	"Cone" shape_size[[depth, radius_bottom, radius_top ,sides]

 - 	pos_x and pos_y are chosen to align with views taken from the North, Top or East faces towards the origin so that they are aligned with [-x,+z], [+x,+y] & [+y,+z] respectively
 - 	rotation is clockwise about the plane of the North, Top or East faces as lookign towards the origin this means they are anticlockwise for the opposite faces(i.e. same convention as above)
 - 	these 2 conventiosn are chosen to make it easier to position cutouts that align with oppsite sides. e.g. a box that fits around a rotated ellipse pipe
	
v2.1 2013-03-22 (AKA cutouts_on_faces_for_loop-v0_1.scad)
 - based on kitlaan's array structure, supporting cones and rectangles
 - from kitlaan's Customisable Electronic Device Packaging http://www.thingiverse.com/thing:8607
 - 	 - 	rect [ x-offset, y-offset, x-width, y-width, corner-radius, corner-sides, depth, rotate ]
 - 	 - 	cylinder [ x-offset, y-offset, inner-radius, outer-radius, sides, depth, rotate ]

 @attention
Copyright (c) 2013 Tyndall National Institute.
All rights reserved.
Permission to use, copy, modify, and distribute this software and its documentation for any purpose, without fee, and without written agreement is hereby granted, provided that the above copyright notice and the following two paragraphs appear in all copies of this software. 

IN NO EVENT SHALL TYNDALL NATIONAL INSTITUTE BE LIABLE TO ANY PARTY FOR DIRECT, INDIRECT, SPECIAL, INCIDENTAL, OR CONSEQUENTIAL DAMAGES ARISING OUT OF THE USE OF THIS SOFTWARE AND ITS DOCUMENTATION, EVEN IF TYNDALL NATIONAL INSTITUTE HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

TYNDALL NATIONAL INSTITUTE SPECIFICALLY DISCLAIMS ANY WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.  THE SOFTWARE PROVIDED HEREUNDER IS ON AN "AS IS" BASIS, AND TYNDALL NATIONAL INSTITUTE HAS NO OBLIGATION TO PROVIDE MAINTENANCE, SUPPORT, UPDATES, ENHANCEMENTS, OR MODIFICATIONS.

*/
a_bit=0.1;
/* [User Controls] */
//dimensions of the object to be packaged
device_xyz=[23,37,18];
//size of the gap between each side of the object and the internal wall of the packaging
clearance_xyz=[0.5,0.25,0.5];//
//how thick the material of the packaging in each direction//recommend keeping X&Y value the same
//wall_thickness_xyz=[2,2,1];//not yet implemented!!!

//how thick the material of the packaging
wall_t=3;//thickness//actual most recent version has a base thickness of 2 but that is not yet fully implemented in this code

//The external radius of the rounded corners of the packaging
corner_radius=clearance_xyz[1]+wall_t;

//How many sides do these rounded corners have?
corner_sides=9;

//How high is the lip that connects the 2 halves?
lip_h=2;

//How tall is the top relative to the bottom
top_bottom_ratio=0.65;

//Does the part have mouse ears or not?
has_mouseears=false;//[true, false]

//how thick the flat discs that make the mosue ears should be (twice print layer thickness is a good idea)
mouse_ear_thickness=0.32*2;

//how large the flat discs that make the mouse ears are (5 to 15 generally work well)
mouse_ear_radius=10;

//the layout of the parts
layout="beside";//[beside, stacked, top, bottom, topflipped, bottomflipped]

//the orientation of the individual top/bottom half parts when generated separately
flipped=true;//[true, false]

//how far apart the 2 halves are in the "beside" layout
separation=4;//2;

//how much of an overlap (-ve) or gap (+ve) is there between the inner and outer lip surfaces, a value of 0 implies they meet perfectly in the middle
lip_fit=0.5;

//does it have an imported representaion of the actual device to be packaged?
has_device=false;//true/false

//what style of box is it
box_type="rounded4sides";//"rounded4sides";//"cuboid","rounded4sides", "rounded6sides", "chamfered6sides"

//data structure defining all the cutouts and depressions used on the packaging
holes = [ //format [face_name, shape_name, shape_position[x_pos,y_pos,x_offs,y_offs,rotate,align], shape_size[depth,,,]]
    ["S", "Rectangle", [0, 0, -1.5, -device_xyz[2]/2+5.5, 0, "inside"], [wall_t, 14, 7]],
    ["W", "Rectangle", [0, 0, -1.5, -device_xyz[2]/2+5.5, 0, "outside"], [wall_t+3, 39, 7]],
    // Reset button; Not needed, really.
    //["T", "Cylinder", [0, 0, 0.5, -device_xyz[1]/2+6, 0, "outside"], [wall_t, 1.5, 10]],
    
	//[depth, length, breadth, corner_radius, corner_sides]
	];


	post_tolerance=0.2;
//data structure defining all the internal supporting structures used on the packaging
posts = [ //format [face_name, shape_name shape_position[x_pos,y_pos,x_offs,y_offs,rotate,align], shape_size[depth,,,]]
	 
	];
//data structure defining all the engraved text used on the packaging
text_engrave_emboss_depth=1;
text_height_big=7;
text_height_small=3;
text_spacing=1.1;
text = [//recessed text on faces [face_name, text_to_write, shape_position[x_pos,y_pos,x_offs,y_offs,rotate,align], shape_size[depth,font_height,font_spacing,mirror]] Note: for silly reasons mirror must be 0 or 1 corresponding to false and true in this version
	//["T", "WIMUv4",		[0,6,0,0,0,"outside"], 				[text_engrave_emboss_depth,text_height_big,text_spacing,0]],
	//["T", "+Z",			[0,-6,0,0,0,"outside"], 			[text_engrave_emboss_depth,text_height_big,text_spacing,0]],

	//["S", "uSD",		[-5,6.5,0,0,0,"outside"], 			[text_engrave_emboss_depth,text_height_small,text_spacing,0]],
	//["E", "I O",		[-10,-4,0,0,0,"outside"], 			[text_engrave_emboss_depth,text_height_small,text_spacing,0]],
	//["E", "USB",		[4,-4,0,0,0,"outside"], 			[text_engrave_emboss_depth,text_height_small,text_spacing,0]],
	//["S", "+X",			[10,0,0,5,0,"outside"], 			[text_engrave_emboss_depth,text_height_big,text_spacing,0]],
	//["N", "-X",			[0,0,0,0,0,"outside"], 				[text_engrave_emboss_depth,text_height_big,text_spacing,0]],
	//["W", "-Y",			[0,0,0,0,0,"outside"], 				[text_engrave_emboss_depth,text_height_big,text_spacing,0]],
	// ["E", "+Y",			[0,0,0,0,0,"outside"], 				[text_engrave_emboss_depth,text_height_big,text_spacing,0]],
	// ["T", "USB",		[21,4,0,0,270,"outside"], 			[text_engrave_emboss_depth,text_height_small,text_spacing,0]],
	// ["T", "O I",		[21,-10,0,0,90,"outside"], 			[text_engrave_emboss_depth,text_height_small,text_spacing,0]],
	// ["T", "uSD",		[-5,-14,0,0,0,"outside"], 			[text_engrave_emboss_depth,text_height_small,text_spacing,0]],
	];


//data structure defining external items such as .stl files to import
items =[//external items on faces [face_name, external_file, shape_position[x_pos,y_pos,x_offs,y_offs,rotate,align], shape_size[depth, scale_x,scale_y, scale_z, mirror]] Note: for silly reasons mirror must be 0 or 1 corresponding to false and true in this version
	//["B", "tyndall_logo_v0_2.stl",			[0,0,0,0,00,"outside"], 					[0.5,10/21.9,10/21.9,1.1/1.62,0]]
	];

//add external slotted flanges for say passing a belt or strap through in Z plane on up to 4 sides
has_flanges=true;//[true, false]
//how thick are the flanges in Z-direction
flange_t=wall_t;
//how "tall" is the slot in the flange i.e. thick is the material you will want to pass through the slot in the flange
slot_t=5;
//how wide is the slot in the flange
slot_w=5;
//define the flanges on each of the four sides
flanges=[
//flange_sides, flange_type, position[x_pos, y_pos], shape_size[flange_t, flange_case_gap, flange_wall_t,slot_b,slot_l,flange_sides]
	["N","rounded_slot",[0,0],	[flange_t,1,wall_t,slot_t,slot_w,corner_sides]],
	["S","rounded_slot",[0,0],	[flange_t,1,wall_t,slot_t,slot_w,corner_sides]],
	// ["E","rounded_slot",[0,0],	[flange_t,0,wall_t,slot_t,slot_w,corner_sides]],
	// ["W","rounded_slot",[0,0],	[flange_t,0,wall_t,slot_t,slot_w,corner_sides]],
	
	//["S","2_holes",		[0,0], 	[flange_t,0,wall_t,hole_center_sep,hole_r,box_s]],//[]
];
/* [Hidden] */
//a small number used for manifoldness
a_bit=0.01;
//x dimension of packaging
box_l=device_xyz[0]+2*(clearance_xyz[0]+wall_t); //box_l=device_xyz[0]+2*(clearance_xyz[0]+wall_thickness_xyz[0]);
//y dimension of packaging
box_b=device_xyz[1]+2*(clearance_xyz[1]+wall_t); //box_b=device_xyz[1]+2*(clearance_xyz[1]+wall_thickness_xyz[1]);
//z dimension of packaging
box_h=device_xyz[2]+2*(clearance_xyz[2]+wall_t);//box_h=device_xyz[2]+2*(clearance_xyz[2]+wall_thickness_xyz[2]);
//join together the 3 relevant mouse ear values
mouse_ears=[has_mouseears, mouse_ear_thickness, mouse_ear_radius];
//join together the 5 relevant box values
box=[box_l,box_b,box_h,corner_radius,corner_sides,wall_t];//

//choose if your packaging has a lockign feature between the lips
has_locking_feature=true;//true, false
//choose the shape of this locking feature
locking_feature_type="spheroid"; //["spheroid", "hexagonal", "groove"]

//for groove type locking features
locking_feature_r=lip_h/4;

//for hexagon type locking features
//radius 1 of locking feature for hexagon type locking features
locking_feature_r1=lip_h/4;
//radius 2 of locking feature for hexagon type locking features
locking_feature_r2=lip_h/6;
//height (actually more like length) of locking features along X-direction for hexagon type locking features
locking_feature_hx=20;
//height (actually more like length) of locking features along Y-direction for hexagon type locking features
locking_feature_hy=15;

//for spheroid type locking feature
//height of locking features above vertical surface for spheroid type locking features
locking_feature_max_h=lip_fit;
//depth of locking features from top to bottom in direction of lip h for spheroid type locking features
locking_feature_max_d=lip_h/2;
//length of locking features along X-direction for spheroid type locking features
locking_feature_max_lx=20;
//length of locking features along Y-direction for spheroid type locking features
locking_feature_max_ly=10;

//********************************includes******************//
use<OpenSCAD_Parametric_Packaging_Script_v2\write.scad>;

//******************************calls**********************//
	make_box(box,corner_radius, corner_sides, lip_h, lip_fit, top_bottom_ratio, mouse_ears, layout, flipped, separation, holes, posts, text, items, has_device, box_type, has_flanges, flanges);

include <OpenSCAD_Parametric_Packaging_Script_v2\make_box_v2_6k.scad>;