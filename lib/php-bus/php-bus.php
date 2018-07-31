<?php

class BusInfo
{
	protected $url;
	protected $cachedir;

	public function __construct($url, $cachedir="")
	{
		$this->url = $url;
		$this->cachedir = $cachedir;
		if(is_dir($cachedir))
		{
			@mkdir(rtrim($cachedir, "/") . "/areas", 0755, true);
			@mkdir(rtrim($cachedir, "/") . "/places", 0755, true);
			@mkdir(rtrim($cachedir, "/") . "/points", 0755, true);
			@mkdir(rtrim($cachedir, "/") . "/routes", 0755, true);
			@mkdir(rtrim($cachedir, "/") . "/services", 0755, true);
			@mkdir(rtrim($cachedir, "/") . "/staticmap", 0755, true);
			@mkdir(rtrim($cachedir, "/") . "/stops", 0755, true);
		}
	}

	public function stop($stopid)
	{
		return new BusStop($stopid, $this->url, $this->cachedir);
	}

	public function stopCollection($label)
	{
		return new BusStopCollection($label, $this->url, $this->cachedir);
	}

	public function route($routeid)
	{
		return new BusRoute($routeid, $this->url, $this->cachedir);
	}

	public function service($serviceid, $operatorid)
	{
		return new BusService($serviceid, $operatorid, $this->url, $this->cachedir);
	}

	public function area($areaid)
	{
		return new BusArea($areaid, $this->url, $this->cachedir);
	}

	public function place($fhrsid)
	{
		return new BusPlace($fhrsid, $this->url, $this->cachedir);
	}

	public function allServices()
	{
		$url = $this->url . "/dump/operators";
		$data = json_decode(file_get_contents($url), true);
		if(!(is_array($data))) { return(array()); }

		$ret = array();
		foreach($data as $operator)
		{
			$noc = $operator['id'];
			$url = $this->url . "/operator/" . $noc;

			$opdata = json_decode(file_get_contents($url), true);
			if(!(is_array($opdata))) { continue; }
			foreach($opdata['services'] as $serviceid)
			{
				$service = new BusService($serviceid, $noc, $this->url, $this->cachedir);
				$ret[] = $service;
			}
		}

		usort($ret, function($a, $b) { return(strnatcmp($a->operator()['name'] . " ". $a->id, $b->operator()['name'] . " " . $b->id)); });

		return($ret);
	}

	public function search($query)
	{
		$json = json_encode(array("search"=>$query, "type"=>""));
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_URL, $this->url . "/resolve");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"Content-Length: " . strlen($json))
		);
		$ret = json_decode(curl_exec($ch), true);
		curl_close($ch);

		if(!(is_array($ret))) { $ret = array(); }

		return($ret);
	}
}

class BusStopCollection
{
	public $label;

	protected $cachedir;
	protected $data;
	protected $url;

	public function __construct($label, $url, $cachedir="")
	{
		$this->url = $url;
		$this->label = $label;
		$this->cachedir = $cachedir;
		$data = array();
	}

	public function addStop($stopid)
	{
		$stop = new BusStop($stopid, $this->url, $this->cachedir);
		$this->data[] = $stop;
	}

	public function stops()
	{
		return($this->data);
	}

	public function buses()
	{
		return(array());
	}
}

class BusPlace
{
	public $id;
	public $label;
	public $address;

	public $lat;
	public $lon;

	protected $cachedir;
	protected $url;
	protected $data;

	public function __construct($fhrsid, $url, $cachedir="")
	{
		$this->url = $url;
		$this->cachedir = $cachedir;
		$this->id = $fhrsid;

		$cache_file = rtrim($cachedir, "/") . "/places/" . $fhrsid . ".json";
		$data = array();
		if(file_exists($cache_file)) { $data = json_decode(file_get_contents($cache_file), true); }
		if(!(is_array($data))) { $data = array(); }
		if(count($data) == 0)
		{
			$data = json_decode(file_get_contents(rtrim($url, "/") . "/place/" . $fhrsid), true);
			if(!(is_array($data))) { $data = array(); }
			$data['stops'] = json_decode(file_get_contents(rtrim($url, "/") . "/place/" . $fhrsid . "/stops"), true);
			if(!(is_array($data['stops']))) { $data['stops'] = array(); }
		}
		if((count($data) > 2) && (!(file_exists($cache_file))))
		{
			$fp = fopen($cache_file, "w");
			fwrite($fp, json_encode($data));
			fclose($fp);
		}

		$this->label = $data['label'];
		$this->address = $data['address'];
		$this->lat = $data['geo']['latitude'];
		$this->lon = $data['geo']['longitude'];
		$this->data = $data;

	}

	public function dump()
	{
		return($this->data);
	}

	public function stops()
	{
		$ret = array();
		foreach($this->data['stops'] as $stop)
		{
			$ns = new BusStop($stop['id'], $this->url, $this->cachedir);
			if(strlen($ns->label) == 0) { continue; }
			$ret[] = $ns;
		}
		return($ret);
	}

	public function buses()
	{
		$ret = array();

		$data = json_decode(file_get_contents(rtrim($this->url, "/") . "/place/" . $this->id . "/buses"), true);

		if(!(is_array($data))) { return($ret); }
		$id = "";
		$bus = array("stops"=>array());
		$lasttime = 0;
		$laststop = "";
		foreach($data as $busstop)
		{
			if(strcmp($id, $busstop['stop_id']) != 0)
			{
				if(strlen($id) > 0)
				{
					$ret[] = $bus;
				}
				$bus = array("stops"=>array());
				$id = $busstop['stop_id'];
			}

			$stop = new BusStop($busstop['stop_id'], $this->url, $this->cachedir);
			$bus['name'] = $busstop['name'];
			$bus['id'] = $busstop['stop_id'];
			$bus['dest'] = $busstop['dest'];
			$bus['route'] = new BusRoute($busstop['route'], $this->url, $this->cachedir);
			if((strcmp($stop->id, $laststop) == 0) && ($lasttime == $busstop['time'])) { continue; }
			$bus['stops'][] = array("stop"=>$stop, "time"=>$busstop['time'], "date"=>$busstop['date']);
			$lasttime = $busstop['time'];
			$laststop = $stop->id;
		}
		$ret[] = $bus;

		usort($ret, function($a, $b)
		{
			$aa = $a['stops'][0]['date'];
			$bb = $b['stops'][0]['date'];
			if($aa < $bb) { return -1; }
			if($aa > $bb) { return 1; }
			return 0;
		} );

		return($ret);
	}
}

class BusArea
{
	public $id;
	public $label;

	protected $cachedir;
	protected $url;
	protected $data;

	public function __construct($areaid, $url, $cachedir="")
	{
		$this->url = $url;
		$this->cachedir = $cachedir;
		$this->id = $areaid;

		$cache_file = rtrim($cachedir, "/") . "/areas/" . $areaid . ".json";
		if(!(file_exists($cache_file))) { $cache_file = "./tmp/areas/" . $areaid . ".json"; }
		$data = array();
		if(file_exists($cache_file)) { $data = json_decode(file_get_contents($cache_file), true); }
		if(!(is_array($data))) { $data = array(); }

		if(count($data) == 0)
		{
			$file = rtrim($url, "/") . "/area/" . $areaid;
			$data = json_decode(file_get_contents($file), true);

			if(!(is_array($data))) { $data = array(); }
			$data['stops'] = json_decode(file_get_contents(rtrim($url, "/") . "/area/" . $areaid . "/stops"), true);
			if(!(is_array($data['stops']))) { $data['stops'] = array(); }
		}
		if((count($data) > 2) && (!(file_exists($cache_file))))
		{
			$fp = fopen($cache_file, "w");
			fwrite($fp, json_encode($data));
			fclose($fp);
		}

		$this->label = $data['label'];
		$this->data = $data;
	}

	public function dump()
	{
		return($this->data);
	}

	public function stops()
	{
		$ret = array();
		foreach($this->data['stops'] as $stop)
		{
			$ns = new BusStop($stop, $this->url, $this->cachedir);
			if(strlen($ns->label) == 0) { continue; }
			$ret[] = $ns;
		}
		return($ret);
	}

	public function buses()
	{
		$ret = array();

		$data = json_decode(file_get_contents(rtrim($this->url, "/") . "/area/" . $this->id . "/buses"), true);

		if(!(is_array($data))) { return($ret); }
		$id = "";
		$bus = array("stops"=>array());
		$lasttime = 0;
		$laststop = "";
		foreach($data as $busstop)
		{
			if(strcmp($id, $busstop['id']) != 0)
			{
				if(strlen($id) > 0)
				{
					$ret[] = $bus;
				}
				$bus = array("stops"=>array());
				$id = $busstop['id'];
			}

			$stop = new BusStop($busstop['stop'], $this->url, $this->cachedir);
			$bus['name'] = $busstop['name'];
			$bus['id'] = $busstop['id'];
			$bus['dest'] = $busstop['dest'];
			$bus['route'] = new BusRoute($busstop['route'], $this->url, $this->cachedir);
			if((strcmp($stop->id, $laststop) == 0) && ($lasttime == $busstop['time'])) { continue; }
			$bus['stops'][] = array("stop"=>$stop, "time"=>$busstop['time'], "date"=>$busstop['date']);
			$lasttime = $busstop['time'];
			$laststop = $stop->id;
		}
		$ret[] = $bus;

		usort($ret, function($a, $b)
		{
			$aa = $a['stops'][0]['date'];
			$bb = $b['stops'][0]['date'];
			if($aa < $bb) { return -1; }
			if($aa > $bb) { return 1; }
			return 0;
		} );

		return($ret);
	}
}

class BusRoute
{
	public $id;
	public $label;
	public $direction;

	protected $cachedir;
	protected $url;
	protected $data;

	public function __construct($routeid, $url, $cachedir="")
	{
		$this->url = $url;
		$this->cachedir = $cachedir;
		$this->id = $routeid;

		$cache_file = rtrim($cachedir, "/") . "/routes/" . $routeid . ".json";
		$data = array();
		if(file_exists($cache_file)) { $data = json_decode(file_get_contents($cache_file), true); }
		if(!(is_array($data))) { $data = array(); }
		if(count($data) == 0)
		{
			$data = json_decode(file_get_contents(rtrim($url, "/") . "/route/" . $routeid), true);
			if(!(is_array($data))) { $data = array(); }
			$data['stops'] = json_decode(file_get_contents(rtrim($url, "/") . "/route/" . $routeid . "/stops"), true);
			if(!(is_array($data['stops']))) { $data['stops'] = array(); }
		}
		if((count($data) > 2) && (!(file_exists($cache_file))))
		{
			$fp = fopen($cache_file, "w");
			fwrite($fp, json_encode($data));
			fclose($fp);
		}

		$this->label = $data['number'] . " " . $data['description'];
		$this->direction = $data['direction'];
		$this->data = $data;
	}

	public function operator()
	{
		$data = $this->data;
		return($data['operator']);
	}

	public function service()
	{
		$service = array();
		$service['number'] = $this->data['number'];
		$service['url'] = "/bus-service/" . strtolower($this->data['operator']['noc']) . "/" . $this->data['number'] . ".html";
		$service['link'] = "<a href=\"" . $service['url'] . "\">" . $service['number'] . "</a>";
		return($service);
	}

	public function dump()
	{
		return($this->data);
	}

	public function stops()
	{
		$ret = array();
		foreach($this->data['stops'] as $stop)
		{
			$ret[] = new BusStop($stop['id'], $this->url, $this->cachedir);
		}
		return($ret);
	}

	public function stopCount()
	{
		return(count($this->data['stops']));
	}

}

class BusStop
{
	public $id;
	public $label;
	public $address;
	public $disambiguation;

	public $latitude;
	public $longitude;

	protected $cachedir;
	protected $url;
	protected $data;

	protected $places;
	protected $services;

	public function __construct($stopid, $url, $cachedir="")
	{
		$this->url = $url;
		$this->cachedir = $cachedir;
		$this->id = $stopid;
		$this->label = "";
		$this->disambiguation = "";

		$cache_file = rtrim($cachedir, "/") . "/stops/" . $stopid . ".json";
		$data = array();
		if(file_exists($cache_file)) { $data = json_decode(file_get_contents($cache_file), true); }
		if(count($data) == 0) { $data = json_decode(file_get_contents(rtrim($url, "/") . "/stop/" . $stopid), true); }
		if(!(is_array($data))) { $data = array(); }
		if(!(array_key_exists("services", $data)))
		{
			$data['services'] = json_decode(file_get_contents(rtrim($url, "/") . "/stop/" . $stopid . "/services"), true);
			if(!(is_array($data['services']))) { $data['services'] = array(); }
		}

		if((count($data) > 0) && (!(file_exists($cache_file))))
		{
			$fp = fopen($cache_file, "w");
			fwrite($fp, json_encode($data));
			fclose($fp);
		}

		$this->data = $data;
		$this->label = "";
		if(array_key_exists("commonname", $data)) { $this->label = $data['commonname']; }
		if(array_key_exists("indicator", $data)) { $this->disambiguation = $data['indicator']; }
		$addr = array();
		if(strlen($data['landmark']) > 0) { $addr[] = $data['landmark']; }
		if(strlen($data['street']) > 0) { $addr[] = $data['street']; }
		if(strlen($data['locality']['localityname']) > 0) { $addr[] = $data['locality']['localityname']; }
		$address = implode(", ", $addr);
		$this->address = $address;
		$this->latitude = $this->data['lat'];
		$this->longitude = $this->data['lon'];
	}

	public function routes()
	{
		$url = rtrim($this->url, "/") . "/stop/" . $this->id . "/services";

		$ret = array();
		foreach(json_decode(file_get_contents($url), true) as $service)
		{
			foreach($service['routes'] as $route)
			{
				$ret[] = new BusRoute($route, $this->url, $this->cachedir);
			}
		}

		usort($ret, function($a, $b)
		{
			$aa = $a->service();
			$bb = $b->service();

			$ret = strnatcmp($aa['number'], $bb['number']);
			if($ret != 0) { return($ret); }

			return($ret);
		});

		return($ret);
	}

	public function buses() { return array(); }

	public function dump()
	{
		$ret = array();
		$ret['status'] = "";
		$ret['label'] = $this->data['commonname'];
		$ret['code'] = $this->data['atcocode'];
		$ret['uri'] = "http://id.southampton.ac.uk/bus-stop/" . $this->data['atcocode'];
		$ret['routes'] = array();
		$ret['stops'] = array();
		$ret['age'] = 0;
		$ret['point'] = array("latitude"=>$this->latitude, "longitude"=>$this->longitude);
		foreach($this->routes() as $route)
		{
			$newroute = array();
			$op = $route->operator();
			$uri = "http://id.southampton.ac.uk/bus-route/" . $route->id;
			$newroute['code'] = preg_replace("/^([^ ]+) (.*)$/", "$1", $route->label);
			$newroute['label'] = preg_replace("/^([^ ]+) (.*)$/", "$2", $route->label);
			$newroute['operator'] = $op['name'];
			$newroute['operator_uri'] = "http://id.southampton.ac.uk/bus-operator/" . $op['noc'];
			$newroute['stop_count'] = count($route->stops());
			$ret['routes'][$uri] = $newroute;
		}
		$ret['raw'] = $this->data;
		return($ret);
	}
}

class BusService
{
	public $id;
	public $label;

	protected $cachedir;
	protected $url;
	protected $data;

	protected function build_data($serviceid, $operator)
	{
		$url = $this->url;
		$data = array();
		$data = json_decode(file_get_contents(rtrim($url, "/") . "/service/" . $operator . "/" . $serviceid), true);
		if(!(is_array($data))) { $data = array(); }
		if(array_key_exists("routes", $data))
		{
			foreach($data['routes'] as &$route)
			{
				$route['service'] = $data['id'];
			}
		}

		return($data);
	}

	public function operator()
	{
		return($this->data['operator']);
	}

	public function routes()
	{
		$ret = array();
		foreach($this->data['routes'] as $route) { $ret[] = new BusRoute($route['id'], $this->url, $this->cachedir); }
		usort($ret, function($a, $b)
		{
			$r = strcmp($a->direction, $b->direction);
			if($r != 0) { return(0 - $r); }

			if(count($a->stops) < count($b->stops)) { return -1; }
			if(count($a->stops) > count($b->stops)) { return 1; }

			return(strcmp($a->label, $b->label));
		});
		return($ret);
	}

	public function dump()
	{
		return($this->data);
	}

	public function __construct($serviceid, $operator, $url, $cachedir="")
	{
		$this->url = $url;
		$this->cachedir = $cachedir;
		$this->id = $serviceid;

		$data = array();
		$cache_file = rtrim($cachedir, "/") . "/services/" . $operator . "_" . $serviceid . ".json";
		if(file_exists($cache_file)) { $data = json_decode(file_get_contents($cache_file), true); }
		if(!(is_array($data))) { $data = array(); }
		if(count($data) == 0) { $data = $this->build_data($serviceid, $operator); }
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
