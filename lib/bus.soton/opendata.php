<?php

function make_stop_graph($stop)
{
	$g = new Graphite();
	$g->ns("naptan", "http://transport.data.gov.uk/def/naptan/");
	$g->ns("transit", "http://vocab.org/transit/terms/");
	$g->ns("soton", "http://id.southampton.ac.uk/ns/");

	$code = $stop->id;
	$uri = "http://id.southampton.ac.uk/bus-stop/" . $code;
	$name = $stop->label;
	$lat = (float) $stop->latitude;
	$lon = (float) $stop->longitude;

	$g->addCompressedTriple($uri, "rdf:type", "http://transport.data.gov.uk/def/naptan/BusStop");
	$g->addCompressedTriple($uri, "rdf:type", "http://vocab.org/transit/terms/Stop");
	$g->addTriple($uri, "http://www.w3.org/2000/01/rdf-schema#label", $name, "literal");
	$g->addTriple($uri, "http://www.w3.org/2003/01/geo/wgs84_pos#lat", $lat, "http://www.w3.org/2001/XMLSchema#float");
	$g->addTriple($uri, "http://www.w3.org/2003/01/geo/wgs84_pos#long", $lon, "http://www.w3.org/2001/XMLSchema#float");
	$g->addTriple($uri, "http://www.w3.org/2004/02/skos/core#notation", $code, "http://id.southampton.ac.uk/ns/bus-stop-id-scheme");
	$g->addTriple($uri, "http://purl.org/openorg/mapIcon", "http://data.southampton.ac.uk/map-icons/Transportation/bus.png");
	$g->addTriple($uri, "http://xmlns.com/foaf/0.1/page", "http://bus.southampton.ac.uk/bus-stop/" . $code . ".html");
	$g->addTriple($uri, "http://id.southampton.ac.uk/ns/mobilePage", "http://bus.southampton.ac.uk/bus-stop-mobile/" . $code . ".html");
	$g->addTriple($uri, "http://id.southampton.ac.uk/ns/liveBusTimes", "http://bus.southampton.ac.uk/bus-stop/" . $code . ".json");

	return($g);
}

function make_route_graph($route)
{
	$g = new Graphite();
	$g->ns("naptan", "http://transport.data.gov.uk/def/naptan/");
	$g->ns("transit", "http://vocab.org/transit/terms/");
	$g->ns("soton", "http://id.southampton.ac.uk/ns/");

	$operator = $route->operator()['noc'];
	$hash = $route->id;
	$dir = $route->direction;
	$service = $route->service()['number'];
	$desc = $route->label;

	$uri = "http://id.southampton.ac.uk/bus-route/" . $hash;
	$url = "http://bus.southampton.ac.uk/bus-route/" . $hash . ".html";

	$g->addCompressedTriple($uri, "rdf:type", "http://id.southampton.ac.uk/ns/BusRoute");
	$g->addCompressedTriple($uri, "rdf:type", "http://vocab.org/transit/terms/BusRoute");
	$g->addCompressedTriple($uri, "foaf:page", $url);
	if(strcmp($dir, "inbound") == 0) { $g->addCompressedTriple($uri, "rdf:type", "http://id.southampton.ac.uk/ns/BusRouteInbound"); }
	if(strcmp($dir, "outbound") == 0) { $g->addCompressedTriple($uri, "rdf:type", "http://id.southampton.ac.uk/ns/BusRouteOutbound"); }

	$g->addTriple($uri, "http://www.w3.org/2000/01/rdf-schema#label", $desc, "literal");
	$g->addTriple($uri, "http://www.w3.org/2004/02/skos/core#notation", $service, "http://id.southampton.ac.uk/ns/bus-route-id-scheme");
	$g->addTriple($uri, "http://id.southampton.ac.uk/ns/busRouteOperator", "http://id.southampton.ac.uk/bus-operator/" . $operator);
	$g->addTriple($uri, "http://vocab.org/transit/terms/agency", "http://id.southampton.ac.uk/bus-operator/" . $operator);

	$i = 1;
	foreach($route->stops() as $stop)
	{
		$stop_uri = $uri . "/" . $i . "-" . $stop->id;
		$stop_id_uri = "http://id.southampton.ac.uk/bus-stop/" . $stop->id;

		$g->addTriple($uri, "http://vocab.org/transit/terms/routeStop", $stop_uri);
		$g->addTriple($stop_uri, "http://vocab.org/transit/terms/sequence", $i, "http://www.w3.org/2001/XMLSchema#nonNegativeInteger");
		$g->addTriple($stop_uri, "http://id.southampton.ac.uk/ns/busRouteSequenceNumber", $i, "http://www.w3.org/2001/XMLSchema#nonNegativeInteger");
		$g->addTriple($stop_uri, "http://id.southampton.ac.uk/ns/busStoppingAt", $stop_id_uri);
		$g->addTriple($stop_uri, "http://id.southampton.ac.uk/ns/inBusRoute", $uri);
		$g->addTriple($stop_uri, "http://vocab.org/transit/terms/route", $uri);
		$g->addTriple($stop_uri, "http://vocab.org/transit/terms/stop", $stop_id_uri);
		$g->addTriple($stop_uri, "http://www.w3.org/1999/02/22-rdf-syntax-ns#type", "http://id.southampton.ac.uk/ns/BusRouteStop");
		$g->addTriple($stop_uri, "http://www.w3.org/1999/02/22-rdf-syntax-ns#type", "http://vocab.org/transit/terms/RouteStop");

		$i++;
	}

	return($g);
}

