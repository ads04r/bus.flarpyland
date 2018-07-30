<?php

class UnilinkRoute extends BusRoute
{
	public function points()
	{
		$url = rtrim($this->url, "/") . "/route/" . $this->id . "/points";
		$points_cache = rtrim($this->cachedir) . "/points/" . $this->id . ".json";
		$points = array();
		if(file_exists($points_cache)) { $points = json_decode(file_get_contents($points_cache), true); }
		if(!(is_array($points))) { $points = array(); }
		if(count($points) == 0)
		{
			$points = json_decode(file_get_contents($url), true);
			if(!(is_array($points))) { $points = array(); }
		}
		if((count($points) > 0) && (!(file_exists($points_cache))))
		{
			$fp = fopen($points_cache, "w");
			fwrite($fp, json_encode($points));
			fclose($fp);
		}

		return($points);
	}

	public function dump()
	{
		$data = $this->data;
		$data['points'] = $this->points();
		return($data);
	}

	public function service()
	{
		$operator = strtolower($this->data['operator']['noc']);
		$service = $this->data['number'];
		if((strcmp($operator, "unil") == 0) && (strcmp($service, "U1N") != 0))
		{
			$service = preg_replace("/^U([0-9])([A-Z])$/", "U$1", $service);
		}
		$ret = array();
		$ret['number'] = $service;
		$ret['url'] = "/bus-service/" . $operator . "/" . $service . ".html";
		$ret['link'] = "<a href=\"" . $ret['url'] . "\">" . $service . "</a>";
		return($ret);
	}
}

class UnilinkService extends BusService
{
	private function unilink_services()
	{
		$url = rtrim($this->url, "/") . "/operator/UNIL";
		$cache = rtrim($this->cachedir) . "/unilink_services.json";

		$data = array();
		if(file_exists($cache))
		{
			$data = json_decode(file_get_contents($cache), true);
		}
		if(!(is_array($data))) { $data = array(); }
		if(count($data) == 0)
		{
			$data = json_decode(file_get_contents($url), true);
		}
		if(!(is_array($data))) { $data = array(); }
		if((count($data) > 0) && (!(file_exists($cache))))
		{
			$fp = fopen($cache, "w");
			fwrite($fp, json_encode($data));
			fclose($fp);
		}

		if(array_key_exists("services", $data))
		{
			return($data['services']);
		}

		return(array());
	}

	protected function build_unilink_data($serviceid, $operator)
	{
		$operator = "unil";
		if(strcmp($serviceid, "U1N") == 0) { $operator = "blus"; }
		$url = $this->url;
		$cachedir = $this->cachedir;

		$services = ['blus/U1N'];
		if(strcmp($operator, "unil") == 0)
		{
			$all_services = $this->unilink_services();
			$services = array();
			foreach($all_services as $service)
			{
				if(preg_match("/^" . $serviceid . "/", $service) == 0) { continue; }
				$services[] = "unil/" . $service;
			}
		}
		$data = array();
		foreach($services as $service)
		{
			$new_data = json_decode(file_get_contents(rtrim($url, "/") . "/service/" . $service), true);
			foreach($new_data['routes'] as &$route)
			{
				$route['service'] = $new_data['id'];
			}
			if(count($data) == 0)
			{
				$data = $new_data;
			} else {
				$data['routes'] = array_merge($data['routes'], $new_data['routes']);
			}
		}

		return($data);
	}

	public function operator()
	{
		$ret = array();
		$ret['noc'] = "UNIL";
		$ret['name'] = "Unilink";
		return($ret);
	}

	public function routes()
	{
		$ret = array();
		foreach($this->data['routes'] as $route) { $ret[] = new UnilinkRoute($route['id'], $this->url, $this->cachedir); }
		return($ret);
	}

	public function __construct($serviceid, $url, $cachedir="")
	{
		$operator = "unil";
		if(strcmp($serviceid, "U1N") == 0) { $operator = "blus"; }
		$this->url = $url;
		$this->cachedir = $cachedir;
		$this->id = $serviceid;

		$data = array();
		$cache_file = rtrim($cachedir, "/") . "/services/" . $operator . "_" . $serviceid . ".json";
		if(file_exists($cache_file)) { $data = json_decode(file_get_contents($cache_file), true); }
		if(!(is_array($data))) { $data = array(); }
		if(count($data) == 0) { $data = $this->build_unilink_data($serviceid, $operator); }
		if((count($data) > 0) && (!(file_exists($cache_file))))
		{
			$fp = fopen($cache_file, "w");
			fwrite($fp, json_encode($data));
			fclose($fp);
		}
		$this->data = $data;
		$this->label = "";
		if(array_key_exists("label", $data)) { $this->label = $data['label']; }
	}
}

class UnilinkInfo extends BusInfo
{
	public function route($routeid)
	{
		return new UnilinkRoute($routeid, $this->url, $this->cachedir);
	}

	public function service($serviceid, $operatorid)
	{
		if((strcmp($operatorid, "unil") == 0) || ((strcmp($operatorid, "blus") == 0) && (strcmp($serviceid, "U1N") == 0)))
		{
			return new UnilinkService($serviceid, $this->url, $this->cachedir);
		}
		return new BusService($serviceid, $operatorid, $this->url, $this->cachedir);
	}

	public function search($query)
	{
		$stopwords = array("the", "road", "a", "inn");
		$terms = explode(" ", preg_replace("/ +/", " ", preg_replace("/[^a-z0-9]/", " ", strtolower($query))));
		$data = json_decode(file_get_contents("search/index.json"), true);
		$retix = array();
		$ret = array();
		foreach($terms as $term)
		{
			if(in_array($term, $stopwords)) { continue; }
			if(!(array_key_exists($term, $data['index']))) { continue; }
			foreach($data['index'][$term] as $i)
			{
				if(in_array($i, $retix)) { continue; }
				$retix[] = $i;
			}
		}
		foreach($retix as $i)
		{
			$newitem = array();
			$item = $data['content'][$i];
			$newitem['label'] = $item['label'];
			$newitem['query'] = $item['uri'];
			$newitem['result'] = $item['stops'];
			$newitem['type'] = 'uri';
			$newitem['diff'] = abs(levenshtein(strtolower($query), strtolower($item['label'])));
			$ret[] = $newitem;
		}

		if(count($ret) == 0) { return(parent::search($query)); }

		usort($ret, function($a, $b)
		{
			if($a['diff'] < $b['diff']) { return -1; }
			if($a['diff'] > $b['diff']) { return 1; }
			return 0;
		});

		$ret = array_slice($ret, 0, 10);

		if($ret[0]['diff'] == 0) { $ret = array_slice($ret, 0, 1); }

		return($ret);

	}
}

function curl_get( $url ) // A nicer version of file_get_contents. We can customise the connect timeout and add a nicer user agent this way.
{
	$timeout = 5;
	if(array_key_exists("timeout", $_GET))
	{
		$timeout = (int) $_GET['timeout'];
	}

	$ch = curl_init();
	$headers["User-Agent"] = "SouthamptonOpenData/1.0";

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	$response = curl_exec($ch);
	curl_close($ch);

	return($response);
}

function atcotonaptan($stopcode)
{
	$url = "http://api.bus.southampton.ac.uk/stop/" . $stopcode;
	$data = json_decode(file_get_contents($url), true);
	return($data['naptancode']);
}

function get_upcoming_buses_unilink($stopcode)
{
	$naptan = atcotonaptan($stopcode);
	$url = "http://www.buscms.com/api/REST/html/departureboard.aspx?clientid=Unilink2015&stopid=" . urlencode($naptan) . "&format=html&sourcetype=siri";
	$in = @curl_get($url);

	if(preg_match("/<table/", $in) == 0)
	{
		return false; // Got an error or failed to connect (or they turned it off again).
	}

	$stops = array();
	$doc = new DOMDocument();
	@$doc->loadHTML(str_replace("\\\"", "\"", $in));

	foreach($doc->getElementsByTagName('tr') as $tr)
	{
		if(!($tr->hasAttribute('class'))) { continue; }
		$class = trim("" . $tr->getAttributeNode('class')->value, "\\\"");
		if(strcmp($class, "rowServiceDeparture") != 0) { continue; }

		$item = array();

		$item['name'] = "";
		$item['dest'] = "";
		$item['time'] = "";
		$item['journey'] = "";

		$now = time() - 600;

		foreach($tr->getElementsByTagName('td') as $td)
		{
			if(!($td->hasAttribute('class'))) { continue; }
			$class = trim("" . $td->getAttributeNode('class')->value, "\\\"");

			if(strcmp($class, "colServiceName") == 0) { $item['name'] = "" . $td->textContent; }
			if(strcmp($class, "colDestination") == 0) { $item['dest'] = "" . $td->textContent; }
			if(strcmp($class, "colDepartureTime") == 0)
			{
				$display = $td->textContent;
				$ds = $display . " Europe/London";
				if(($td->hasAttribute('data-departuretime')) || ($td->hasAttribute('data-departureTime')))
				{
					if($td->hasAttribute('data-departuretime')) { $arg = $td->getAttributeNode('data-departuretime'); }
					if($td->hasAttribute('data-departureTime')) { $arg = $td->getAttributeNode('data-departureTime'); }
					$ds = trim("" . $arg->value, "\\\"");
					$ds = preg_replace("|^([0-9]+)/([0-9]+)/([0-9]+) ([0-9]+):([0-9]+):([0-9]+)|", "$3-$2-$1 $4:$5:$6", $ds) . " Europe/London";
				}
				$dt = strtotime($ds);
				if($dt < $now) { $dt = $dt + 86400; }
				$item['date'] = $dt;
				$item['time'] = date("H:i", $dt);
				if((preg_match("/^([0-9]+) min/", $display) > 0) || (strcmp(strtolower($display), "min") == 0)) { $item['vehicle'] = "unknown"; }
			}
		}

		$stops[] = $item;
	}

	usort($stops, function($a, $b)
	{
		if($a['date'] < $b['date']) { return -1; }
		if($a['date'] > $b['date']) { return 1; }
		return 0;
	});

	return($stops);
}

function get_upcoming_buses_jmw($stopcode)
{
	$url = "http://southampton.jmwrti.co.uk:8080/RTI-SIRI-Server/SIRIPublic?format=html&dataType=stopTimes&stopCode=" . $stopcode . "&previewInterval=180";
	$in = "";
	$in = @curl_get($url);

	if(preg_match("/<table/", $in) == 0)
	{
		return false; // Got an error or failed to connect (or they turned it off again).
	}

	$stops = array();
	$laststring = "";
	$i = 0;
	$reg = "<tr.*>.*</tr>";
	$shown = array();
	if(preg_match_all("|" . $reg . "|siU", $in, $matches)) {
		$match = $matches[0];
		foreach($match as $matchline) {
			if(strcmp($matchline, $laststring) != 0)
			{
				$reg = "<td[^>]*>([^<]*)<";
				if ( preg_match_all("|" . $reg . "|siU", $matchline, $m, PREG_SET_ORDER) ) {
					$item = array();
					$item['name'] = $m[0][1];
					$item['dest'] = $m[2][1];
					$item['time'] = substr($m[5][1], 0, 5);
					if(strlen($m[4][1]) > 0) {
							$item['vehicle'] = $m[4][1];
					}
					if(strlen($m[1][1]) > 0) {
							$item['journey'] = $m[1][1];
					}
					if($i > 0)
					{
						$lastjny = "" . @$stops[$i - 1]['journey'];
						$journey = "" . @$item['journey'];
						if(strcmp($journey, $lastjny) == 0)
						{
							$veh = "" . @$item['vehicle'];
							$time = $item['time'];
							if(strlen($veh) > 0)
							{
								$stops[$i - 1]['vehicle'] = $veh;
								$stops[$i - 1]['time'] = $time;
							}
							continue;
						}
					}
					$stops[$i] = $item;
					$i++;
				}
			}
			$laststring = $matchline;
		}
	}

	return($stops);
}

function get_other_buses($stopcode)
{
	// "Live" times are only for UNIL and BLUS buses. This handles the others.

	$url = "http://api.bus.southampton.ac.uk/stop/" . $stopcode . "/buses";
	$data = json_decode(file_get_contents($url), true);
	$ret = array();
	foreach($data as $item)
	{
		$operator = preg_replace("#^([^/]+)/.*$#", "$1", $item['id']);
		if(strcmp($operator, "BLUS") == 0) { continue; }
		if(strcmp($operator, "UNIL") == 0) { continue; }

		$bus = array();
		$bus['name'] = $item['name'];
		$bus['dest'] = $item['dest'];
		$bus['date'] = $item['date'];
		$bus['time'] = date("H:i", $item['date']);
		$bus['journey'] = $item['journey'];
		$ret[] = $bus;
	}
	return($ret);
}

function get_upcoming_buses($stopcode)
{
	$ret = get_upcoming_buses_unilink($stopcode);
	$ret = array_merge($ret, get_other_buses($stopcode));
	usort($ret, function($a, $b)
	{
		if($a['date'] < $b['date']) { return -1; }
		if($a['date'] > $b['date']) { return 1; }
		return 0;
	});

	return(array_slice($ret, 0, 10));
}

function resort_bus_stop_routes($routes)
{
	$ret = array();
	foreach($routes as $id=>$route)
	{
		$route['id'] = $id;
		$ret[] = $route;
	}
	usort($ret, function($a, $b)
	{
		return(strcmp($a['code'], $b['code']));
	});

	return($ret);
}
