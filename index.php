<?php

include("includes/bootloader.php");

$api = new Base($cache);

$api->format = (isset($_GET['format']) && in_array($_GET['format'], array("xml", "json"))) ? strtolower(trim($_GET['format'])) : "xml";
$api->debug = (isset($_GET['debug']));

$api->output_headers();

echo $api->output_error(303);

?>