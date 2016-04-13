wall=2;
sensor_height=4.5;
sensor_sit_depth=6;
sensor_width=11;
handle_size=20;
wire_d=4;

difference() {
    shape_outline();
    // wire hole
    translate([0,0,-handle_size]) cylinder(h=100, d=wire_d, $fn=20);
    // sensor cavity
    translate([-sensor_width/2,1-sensor_height/2,wall+0.1]) cube([sensor_width, sensor_height, sensor_sit_depth]);
}


module shape_outline() {
    cylinder_d=sensor_width+wall*2;
    cone_multiplier = 1.2;
    union() {
        // Inside vent sensor screw-in
        cylinder(d2=cylinder_d, d1=cylinder_d*cone_multiplier, h=sensor_sit_depth+wall);
        // Outside handle body
        translate([0,0,-handle_size]) cylinder(d2=cylinder_d*cone_multiplier, d1=cylinder_d/cone_multiplier, h=handle_size);
        // Twist grip
        translate([-cylinder_d*cone_multiplier/2,-wall/2,-handle_size]) cube([cylinder_d*cone_multiplier, wall, handle_size]);
    }
}