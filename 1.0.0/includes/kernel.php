<?php

include("classes/api.class.php");

$api = new API($cache);

$api->format = (isset($_GET['format']) && in_array($_GET['format'], array("xml", "json"))) ? strtolower(trim($_GET['format'])) : "xml";
$api->version = "1.0.0";
$api->debug = (isset($_GET['debug']));
$api->cookie_file = COOKIE_FILE;
$api->debug_file = DEBUG_FILE;
$api->stack_trace_file = STACK_TRACE_FILE;

$api->init(XBOX_EMAIL, XBOX_PASSWORD);

?>