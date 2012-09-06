<?php

include("../includes/bootloader.php");
include("includes/kernel.php");
$api->output_headers();

$gamertag = (isset($_GET['gamertag']) && !empty($_GET['gamertag'])) ? trim($_GET['gamertag']) : null;

if(!$api->logged_in) {
    echo $api->output_error(500);
} else {
    if(empty($gamertag)) {
        echo $api->output_error(301);
    } else {
        $data = $api->fetch_profile($gamertag);
        if($data) {
            echo $api->output_payload($data);
        } else {
            echo $api->output_error($api->error);
        }
    }
}

?>