<?php

date_default_timezone_set("Europe/London");

include_once("./lib/arc2/ARC2.php");
include_once("./lib/graphite/Graphite.php");
include_once("./lib/php-bus/php-bus.php");
include_once("./lib/bus.soton/unilink.php");
include_once("./lib/bus.soton/opendata.php");
$f3 = require("./lib/fatfree/lib/base.php");
$f3->set('page_load_start', microtime(true));
if(preg_match("/\\.dev\\./", $_SERVER['HTTP_HOST']) > 0)
{
	$f3->set('DEBUG', TRUE);
} else {
	$f3->set('DEBUG', FALSE);
}
$f3->set('TEMP', './tmp/');
$f3->set('data', new UnilinkInfo("https://api.bus.flarpyland.com/", "./tmp"));
// $f3->set('ONERROR', function($f3){ errorHandler($f3); });

$f3->route("GET|HEAD /bus-stop/@stopid.html", function($f3, $params)
{
	$f3->set('template', 'bus-stop.html');
	$f3->set('page_data', $f3->get('data')->stop($params['stopid']));
	$f3->set('stop_data', get_upcoming_buses($params['stopid']));
	echo Template::instance()->render("templates/index.html");
	//if(strcmp($params['format'], "json") == 0) { print(json_encode($f3->get('stop_data'), JSON_PRETTY_PRINT)); }
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
	$url = "http://bus.flarpyland.com/lib/staticmaplite/staticmap.php?maptype=watercolor&center=" . $stop->latitude . "," . $stop->longitude . "&zoom=15&size=100x100";
	$png = file_get_contents($url);
	header("Content-type: image/png");
	print($png);
});
$f3->route("GET|HEAD /bus-stop/@stopid.ttl", function($f3, $params)
{
	$stop = $f3->get('data')->stop($params['stopid']);
	$g = make_stop_graph($stop);
	header("Content-type: application/turtle");
	print($g->serialize("Turtle"));
});
$f3->route("GET|HEAD /bus-stop/@stopid.rdf", function($f3, $params)
{
	$stop = $f3->get('data')->stop($params['stopid']);
	$g = make_stop_graph($stop);
	header("Content-type: application/rdf+xml");
	print($g->serialize("RDFXML"));
});
$f3->route("GET|HEAD /bus-stop-publicdisplay/@stopid.html", function($f3, $params)
{
	$f3->set('template', 'bus-stop-display.html');
	$f3->set('page_data', $f3->get('data')->stop($params['stopid']));
	$f3->set('stop_data', get_upcoming_buses($params['stopid']));
	echo Template::instance()->render("templates/index-display.html");
});
$f3->route("GET|HEAD /bus-stop-iframe/@stopid.html", function($f3, $params)
{
	$f3->set('template', 'bus-stop-iframe.html');
	$f3->set('page_data', $f3->get('data')->stop($params['stopid']));
	$f3->set('stop_data', get_upcoming_buses($params['stopid']));
	echo Template::instance()->render("templates/bus-stop-iframe.html");
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
$f3->route("GET|HEAD /bus-route/@routeid.ttl", function($f3, $params)
{
	$route = $f3->get('data')->route($params['routeid']);
	$g = make_route_graph($route);
	header("Content-type: application/turtle");
	print($g->serialize("Turtle"));
});
$f3->route("GET|HEAD /bus-route/@routeid.rdf", function($f3, $params)
{
	$route = $f3->get('data')->route($params['routeid']);
	$g = make_route_graph($route);
	header("Content-type: application/rdf+xml");
	print($g->serialize("RDFXML"));
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
$f3->route("GET|HEAD /area-publicdisplay/@areaid.html", function($f3, $params)
{
	$f3->set('template', 'bus-area-display.html');
	$f3->set('page_data', $f3->get('data')->area($params['areaid']));
	echo Template::instance()->render("templates/index-display.html");
});
$f3->route("GET|HEAD /bus-routes.html", function($f3)
{
	$f3->set('template', 'bus-routes.html');
	$f3->set('page_data', $f3->get('data')->allServices());
	echo Template::instance()->render("templates/index.html");
});
$f3->route("GET|HEAD /tile/@z/@x/@y.png", function($f3, $params)
{
	$url = "http://a.tile.stamen.com/watercolor/" . $params['z'] . "/" . $params['x'] . "/" . $params['y'] . ".png";
	$png = file_get_contents($url);
	header("Content-type: image/png");
	echo $png;
});
$f3->route("POST /search.@format", function($f3, $params)
{
	$ret = $f3->get('data')->search($_POST['bus_search']);

	if(count($ret) == 1)
	{
		$item = $ret[0];
		$type = $item['type'];
		$id = $item['query'];
		$stops = $item['result'];
		$label = $item['label'];

		if(strcmp($type, "stop") == 0) { $f3->reroute("/bus-stop/" . $id . ".html"); }

		if((strcmp($type, "street") == 0) || (strcmp($type, "stop-area") == 0))
		{
			$stopcollection = $f3->get('data')->stopCollection($label);
			foreach($stops as $stop)
			{
				$stopcollection->addStop($stop);
			}
			$f3->set('template', 'bus-stop-collection.html');
			$f3->set('page_data', $stopcollection);
			echo Template::instance()->render("templates/index.html");
			return;
		}

		if(strcmp($type, "postcode") == 0)
		{
			$stopcollection = $f3->get('data')->stopCollection(preg_replace("/[^0-9A-Z]/", "", strtoupper($item['query'])));
			foreach($stops as $stop)
			{
				$stopcollection->addStop($stop);
			}
			$f3->set('template', 'postcode.html');
			$f3->set('page_data', $stopcollection);
			echo Template::instance()->render("templates/index.html");
			return;
		}
	}

	foreach($ret as &$item)
	{
		if(strcmp($item['type'], "stop") == 0) { unset($item['result']); continue; }
		if(strcmp($item['type'], "fhrs") == 0) { unset($item['result']); continue; }

		$stops = array();
		foreach($item['result'] as $stop)
		{
			$stops[] = $f3->get('data')->stop($stop);
		}
		$item['result'] = $stops;
	}

	$f3->set('template', 'search-results.html');
	$f3->set('page_search_query', $_POST['bus_search']);
	$f3->set('page_data', $ret);
	echo Template::instance()->render("templates/index.html");
});
$f3->route("GET|HEAD /", function($f3, $params)
{
	$f3->set('template', 'home.html');
	echo Template::instance()->render("templates/index.html");
});

$f3->route("GET /U@service", function($f3, $params) { $f3->reroute("/bus-service/unil/U" . $params['service'] . ".html"); });

// Now redirect all the old 'mobile' pages to their nice responsive equivalents

$f3->route("GET /mobile", function($f3) { $f3->reroute("/"); });
$f3->route("GET /bus-route-mobile/@routecode.@format", function($f3, $params) { $f3->reroute("/bus-route/" . $params['routecode'] . "." . $params['format']); });
$f3->route("GET /bus-stop-mobile/@stopcode.@format", function($f3, $params) { $f3->reroute("/bus-stop/" . $params['stopcode'] . "." . $params['format']); });
$f3->route("GET /area-mobile/@areaid.@format", function($f3, $params) { $f3->reroute("/area/" . $params['areaid'] . "." . $params['format']); });

$f3->route("GET *", function($f3, $params)
{
	$url = $params["*"];
	if(preg_match("|^/([^\\.]+)$|", $url) > 0)
	{
		$f3->reroute($url . ".html");
	}
	else
	{
		$f3->error(404);
	}
});

$f3->run();
