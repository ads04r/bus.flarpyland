<?php

date_default_timezone_set("Europe/London");

include_once("./lib/php-bus/php-bus.php");
include_once("./lib/bus.soton/unilink.php");
$f3 = require("./lib/fatfree/lib/base.php");
$f3->set('page_load_start', microtime(true));
$f3->set('DEBUG', 1);
$f3->set('TEMP', '/tmp/');
$f3->set('data', new UnilinkInfo("http://api.bus.southampton.ac.uk/", "/tmp"));
// $f3->set('ONERROR', function($f3){ errorHandler($f3); });

$f3->route("GET|HEAD /bus-stop/@stopid.@format", function($f3, $params)
{
	$f3->set('template', 'bus-stop.html');
	$f3->set('page_data', $f3->get('data')->stop($params['stopid']));
	$f3->set('stop_data', get_upcoming_buses($params['stopid']));
	if(strcmp($params['format'], "html") == 0) { echo Template::instance()->render("templates/index.html"); }
	if(strcmp($params['format'], "json") == 0) { print(json_encode($f3->get('stop_data'), JSON_PRETTY_PRINT)); }
});
$f3->route("GET|HEAD /bus-stop/@stopid.json", function($f3, $params)
{
	$stop = $f3->get('data')->stop($params['stopid']);
	$dump = $stop->dump();
	$dump['stops'] = get_upcoming_buses($stop->id);

	header("Content-type: application/json");
	echo json_encode($dump);
});
$f3->route("GET|HEAD /bus-stop/@stopid.png", function($f3, $params)
{
	$stop = $f3->get('data')->stop($params['stopid']);
	$url = "http://bus.flarpyland.com/lib/staticmaplite/staticmap.php?center=" . $stop->latitude . "," . $stop->longitude . "&zoom=16&size=100x100";
	$png = file_get_contents($url);
	header("Content-type: image/png");
	print($png);
});
$f3->route("GET|HEAD /bus-service/@operatorid/@servicename.html", function($f3, $params)
{
	$f3->set('template', 'bus-service.html');
	$f3->set('page_data', $f3->get('data')->service($params['servicename'], $params['operatorid']));
	echo Template::instance()->render("templates/index.html");
});
$f3->route("GET|HEAD /bus-route/@routeid.html", function($f3, $params)
{
	$f3->set('template', 'bus-route.html');
	$f3->set('page_data', $f3->get('data')->route($params['routeid']));
	echo Template::instance()->render("templates/index.html");
});
$f3->route("GET|HEAD /place/@fhrs.html", function($f3, $params)
{
	$f3->set('template', 'fhrs-place.html');
	$f3->set('page_data', $f3->get('data')->place($params['fhrs']));
	echo Template::instance()->render("templates/index.html");
});
$f3->route("GET|HEAD /area/@areaid.html", function($f3, $params)
{
	$f3->set('template', 'bus-area.html');
	$f3->set('page_data', $f3->get('data')->area($params['areaid']));
	echo Template::instance()->render("templates/index.html");
});
$f3->route("GET|HEAD /bus-routes.html", function($f3)
{
	$f3->set('template', 'bus-routes.html');
	$f3->set('page_data', $f3->get('data')->allServices());
	echo Template::instance()->render("templates/index.html");
});
$f3->route("GET|HEAD /tile/@z/@x/@y.png", function($f3, $params)
{
	$url = "https://tiles.maps.southampton.ac.uk/map/" . $params['z'] . "/" . $params['x'] . "/" . $params['y'] . ".png";
	$png = file_get_contents($url);
	header("Content-type: image/png");
	echo $png;
});
$f3->route("POST /search.@format", function($f3, $params)
{
	$ret = $f3->get('data')->search($_POST['bus_search']);

	header("Content-type: application/json");
	print(json_encode($ret));

});
$f3->route("GET|HEAD /", function($f3, $params)
{
	$f3->set('template', 'home.html');
	echo Template::instance()->render("templates/index.html");
});

// Now redirect all the old 'mobile' pages to their nice responsive equivalents

$f3->route("GET /mobile", function($f3) { $f3->reroute("/"); });
$f3->route("GET /bus-route-mobile/@routecode.@format", function($f3, $params) { $f3->reroute("/bus-route/" . $params['routecode'] . "." . $params['format']); });
$f3->route("GET /bus-stop-mobile/@stopcode.@format", function($f3, $params) { $f3->reroute("/bus-stop/" . $params['stopcode'] . "." . $params['format']); });
$f3->route("GET /area-mobile/@areaid.@format", function($f3, $params) { $f3->reroute("/area/" . $params['areaid'] . "." . $params['format']); });

$f3->run();
