<?php

include("classes/cache.class.php");
include("classes/base.class.php");

/*!
 * Define the caching engine to be used.
 * Supports: apc, memcached, xcache, disk
 */
define(CACHE_ENGINE, "apc");

/*!
 * Define the account details.
 */
define(XBOX_EMAIL, "");
define(XBOX_PASSWORD, "");

/*!
 * Define some log file locations.
 */
define(COOKIE_FILE, "../includes/login_cookies.jar");
define(DEBUG_FILE, "../includes/debug.log");
define(STACK_TRACE_FILE, "../includes/stack_trace.log");

/*!
 * Initiate the caching engine.
 */
$cache = new Cache(CACHE_ENGINE);

?>