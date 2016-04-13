wall_thickness=2;
// Piece that will be rotated
damper_arm_diameter=9;
damper_arm_cut_width=5.1;
damper_arm_depth=12;
// stepper mount
stepper_shaft_diameter=5.8;
stepper_shaft_cut_width=3.5;
stepper_shaft_depth=7;
// Marker for closed position
marker_size=2;
marker_distance=9;
marker_height=5;
// Connecting the two
mount_length=1.2*(damper_arm_depth+stepper_shaft_depth);
mount_diameter=max(damper_arm_diameter,stepper_shaft_diameter)+wall_thickness*2;
// Pin offset degrees to achieve fully closed vent on button press
pin_degrees_offset=0;

/* 
 * motor-to-damper adaptor
**/
//translate([0, mount_diameter, -mount_length]) motor_to_damper_adaptor();

module motor_to_damper_adaptor() {
    rotate([0,0,pin_degrees_offset]) button_pin();
    difference() {
        // main body
        cylinder(mount_length, mount_diameter/2, mount_diameter/2);
        // Stepper cutout
        translate([0,0,stepper_shaft_depth/2]) mount_cutout(stepper_shaft_diameter/2, stepper_shaft_cut_width, stepper_shaft_depth);
        // Damper cutout
        translate([0,0,mount_length-damper_arm_depth/2]) mount_cutout(damper_arm_diameter/2, damper_arm_cut_width, damper_arm_depth);
    }
    
}

module button_pin() {
    translate([mount_diameter/2,0,mount_length-marker_distance]) scale([2*marker_size/marker_height, 0.6, 1]) sphere(d=marker_height, $fn=50);
    translate([-mount_diameter/2,0,mount_length-marker_distance]) scale([2*marker_size/marker_height, 0.6, 1]) sphere(d=marker_height, $fn=50);
}

// Motor mount
vent_type="squares"; // round or square
vent_diameter = 6*25.4; // 6"
shaft_offset=8;
motor_ears_distance=35;
motot_ears_hole_diameter=3.5;
motor_ears_screw_length=15;
vent_mount_distance=55;
vent_mount_hole_diameter=6;
motor_to_vent_distance=mount_length+4-wall_thickness*1.5; // 4mm distance between connector and motor body
vent_ears_total_length=vent_mount_distance+vent_mount_hole_diameter+wall_thickness*2;
vent_ears_total_width=mount_diameter+wall_thickness*4;
motor_screw_cube_side=motot_ears_hole_diameter+wall_thickness*2;
/*
 * mount bracket itself
**/
rotate([90,0,0]) motor_to_vent_mount();

// endstop sensor
endstop_screw_diameter=1.8;
endstop_screw1_distance=16;
endstop_screw1_offset=0;
endstop_screw2_distance=5;
endstop_screw2_offset=21.5;

// Control board
board_width=23.5;
board_length=37.5;
board_latch_height=3.5;
holder_offset=3;

// Not a good idea after all
//translate([motor_ears_distance/2+motor_screw_cube_side/2,shaft_offset+motor_screw_cube_side/2,wall_thickness]) rotate([90,90,0]) controller_mount();

module controller_mount() {
    difference(){
        cube([board_width+wall_thickness*2, board_length+wall_thickness*2, board_latch_height+wall_thickness]);
        translate([wall_thickness, wall_thickness, wall_thickness]) cube([board_width, board_length, board_latch_height]);
        // Cutout for holder to be more flexible
        translate([holder_offset,0,wall_thickness]) cube([wall_thickness,board_length+wall_thickness*2, board_latch_height]);
        translate([holder_offset+wall_thickness*2,0,wall_thickness]) cube([wall_thickness,board_length+wall_thickness*2, board_latch_height]);
        translate([board_width-holder_offset+wall_thickness,0,wall_thickness]) cube([wall_thickness,board_length+wall_thickness*2, board_latch_height]);
        translate([board_width-holder_offset-wall_thickness,0,wall_thickness]) cube([wall_thickness,board_length+wall_thickness*2, board_latch_height]);
    }
    // board holder
    //translate([wall_thickness+holder_offset,wall_thickness,board_latch_height+wall_thickness]) rotate([0,0,270]) board_holder();
    //translate([board_width-holder_offset,wall_thickness,board_latch_height+wall_thickness]) rotate([0,0,270]) board_holder();
    //translate([2*wall_thickness+holder_offset,board_length+wall_thickness,board_latch_height+wall_thickness]) rotate([0,0,90]) board_holder();
    //translate([wall_thickness+board_width-holder_offset,board_length+wall_thickness,board_latch_height+wall_thickness]) rotate([0,0,90]) board_holder();
}

module board_holder() {
    union() {
        cube(wall_thickness, wall_thickness, wall_thickness);
        translate([0,wall_thickness, wall_thickness/2])rotate([90,0,0]) cylinder(d=wall_thickness, h=wall_thickness, $fn=20);
    }
}


module motor_to_vent_mount() {
    union() {
        // Vent ears
        vent_mount_plate();
        // motor mount
        translate([motor_ears_distance/2,shaft_offset,-motor_to_vent_distance]) motor_screw_connector();
        translate([-motor_ears_distance/2,shaft_offset,-motor_to_vent_distance]) motor_screw_connector();
    }
}

module damper_mount_cutout() {
    intersection() {
        cylinder(damper_arm_depth, damper_arm_diameter, damper_arm_diameter, center=true, $fn=40);
        cube([damper_arm_diameter*2, damper_arm_cut_width, damper_arm_depth], center=true);
    }
}

module mount_cutout(radius, width, depth) {
    intersection() {
        cylinder(depth, radius, radius, center=true, $fn=40);
        cube([radius*2, width, depth], center=true);
    }
}

module vent(length) {
    if (vent_type=="round") {
        cylinder(length, vent_diameter/2, vent_diameter/2, $fn=100);
    } else if (vent_type=="square") {
    } else {
        // How do you make it visible?
        echo("Unknown vent type");
    }
}

module vent_mount_plate() {
    endstop_sensor_space=max(endstop_screw1_distance,endstop_screw2_distance)+endstop_screw_diameter/2+wall_thickness-(vent_ears_total_width/2-mount_diameter/2);
    //                   ^ distance to furthest hole                          ^ mounting holes w/ walls                ^ distance from original edge to shaft
    plate_total_width=vent_ears_total_width+motor_screw_cube_side/2+shaft_offset-vent_ears_total_width/2+(endstop_sensor_space) ;
    //                 ^ base width         ^ extend for motor mount                                     ^ accommodate endstop sensor
    translate([-vent_ears_total_length/2,0,0]) rotate([0,90,0]) translate([-wall_thickness*1.5,0,0]) intersection() {
        difference() {
            translate([-wall_thickness*1.5, -vent_ears_total_width/2-endstop_sensor_space, 0]) cube([wall_thickness*3, plate_total_width, vent_ears_total_length]);
            translate([-vent_diameter/2, 0, 0]) vent(vent_ears_total_length);
            // Mount holes cutout
            translate([-wall_thickness*1.5,0,vent_mount_hole_diameter/2+wall_thickness]) rotate([0,90,0]) cylinder(wall_thickness*3, vent_mount_hole_diameter/2, vent_mount_hole_diameter/2, $fn=20);
            translate([-wall_thickness*1.5,0,vent_ears_total_length-vent_mount_hole_diameter/2-wall_thickness]) rotate([0,90,0]) cylinder(wall_thickness*3, vent_mount_hole_diameter/2, vent_mount_hole_diameter/2, $fn=20);
            // shaft cutout
            #translate([-wall_thickness*1.8,0,vent_ears_total_length/2]) rotate([0,90,0]) shaft_cutout();
            // endstop sensor mount
            #translate([-5,-endstop_screw1_distance-mount_diameter/2,vent_ears_total_length/2+endstop_screw1_offset]) rotate([0,90,0]) cylinder(10, d=endstop_screw_diameter, $fn=20);
            #translate([-5,-endstop_screw2_distance-mount_diameter/2,vent_ears_total_length/2+endstop_screw2_offset]) rotate([0,90,0]) cylinder(10, d=endstop_screw_diameter, $fn=20);
        }
    }
}

module shaft_cutout() {
    hull() {
        cylinder(wall_thickness*4, mount_diameter/2+wall_thickness, mount_diameter/2+wall_thickness);
        // To make it easier to 3d print (less drastic overhang)
        translate([0,-mount_diameter-wall_thickness,0]) cylinder(d=1, h=wall_thickness*4);
    }
}

module motor_screw_connector() {
    difference() {
        translate([-motor_screw_cube_side/2, -motor_screw_cube_side/2, 0]) cube([motor_screw_cube_side, motor_screw_cube_side, motor_to_vent_distance]);
        cylinder(motor_ears_screw_length, motot_ears_hole_diameter/2, motot_ears_hole_diameter/2, $fn=20);
    }

}