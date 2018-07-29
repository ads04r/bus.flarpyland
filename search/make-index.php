#!/usr/bin/php -q
<?php

include_once(dirname(dirname(__FILE__)) . "/lib/arc2/ARC2.php");
include_once(dirname(dirname(__FILE__)) . "/lib/graphite/Graphite.php");

function ll_search($lat, $lon)
{
        $json = json_encode(array("search"=>($lat . "," . $lon), "type"=>"latlon"));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_URL, "http://api.bus.southampton.ac.uk/resolve");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "Content-Length: " . strlen($json))
        );
        $ret = json_decode(curl_exec($ch), true);
        curl_close($ch);

	return($ret);
}

$g = new Graphite();
$g->ns("geo", "http://www.w3.org/2003/01/geo/wgs84_pos#");
$g->ns("soton", "http://id.southampton.ac.uk/ns/");
$g->load("http://id.southampton.ac.uk/dataset/places/latest.ttl");
$g->load("http://id.southampton.ac.uk/dataset/southampton-areas/latest.ttl");
$g->load("http://id.southampton.ac.uk/dataset/southampton-things/latest.ttl");

$ret = array();

foreach($g->allSubjects() as $res)
{
	$uri = "" . $res;
	if($res->isType('soton:HiddenFromLists')) { continue; }
	if(!($res->has('geo:lat'))) { continue; }
	if(!($res->has('geo:long'))) { continue; }
	$label = "" . $res->label();
	if(strlen($label) == 0) { continue; }
	if(strcmp($label, "[NULL]") == 0) { continue; }

	$lat = (float) ("" . $res->get('geo:lat'));
	$lon = (float) ("" . $res->get('geo:long'));

	$search = ll_search($lat, $lon);
	if(count($search) != 1) { continue; }
	if(!(array_key_exists("result", $search[0]))) { continue; }
	$stops = $search[0]['result'];
	if(count($stops) == 0) { continue; }

	$item = array();
	$item['uri'] = $uri;
	$item['label'] = $label;
	$item['lat'] = $lat;
	$item['lon'] = $lon;
	$item['stops'] = $stops;
	$ret[] = $item;

	$m = array();
	if(preg_match("#^http://id\\.southampton\\.ac\\.uk/building/([0-9A-Z]+)$#", $uri, $m) == 0) { continue; }
	if(preg_match("#^building #", strtolower($label)) > 0) { continue; }

	$item = array();
	$item['uri'] = $uri;
	$item['label'] = "Building " . $m[1];
	$item['lat'] = $lat;
	$item['lon'] = $lon;
	$item['stops'] = $stops;
	$ret[] = $item;
}

$things = $ret;
$index = array();
$ret = array();
for($i = 0; $i < count($things); $i++)
{
	$thing = $things[$i];
	$labels = explode(" ", preg_replace("/ +/", " ", preg_replace("/([^a-z0-9]+)/", " ", strtolower($thing['label']))));
	foreach($labels as $label)
	{
		if(!(array_key_exists($label, $index))) { $index[$label] = array(); }
		if(in_array($i, $index[$label])) { continue; }
		$index[$label][] = $i;
	}
}

$ret['index'] = $index;
$ret['content'] = $things;
print(json_encode($ret, JSON_PRETTY_PRINT));
