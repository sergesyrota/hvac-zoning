/*
   Useful tool to find 2 spots opposite of each other on round ductowk
**/

// Wall thickness for the tool (make sure it's ridgid)
wall=5;
// Vent diameter, in millimiters; Using helper function, you can also specify radius or circumference
vent_diameter=get_diameter(circumference=510);

function get_diameter (diameter, radius, circumference) 
    = (diameter!=undef ? diameter : (radius != undef ? radius*2 : circumference/3.141592));

difference() {
    cylinder(wall, d=vent_diameter+wall*4, $fn=100);
    cylinder(wall, d=vent_diameter, $fn=100);
    translate([-vent_diameter/2-wall*2,0,0]) cube([vent_diameter+wall*4,vent_diameter/2+wall*2,wall]);
}