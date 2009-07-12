<?php
class Router {
  
  var $routes = array();

// List of action prefixes used in connected routes
  var $__prefixes = array();

/**
 * 'Constant' regular expression definitions for named route elements
 *
 * @var array
 * @access private
 */
  	var $__named = array(
  		'Action'	=> 'index|show|add|create|edit|update|remove|del|delete|view|item',
  		'Year'		=> '[12][0-9]{3}',
  		'Month'		=> '0[1-9]|1[012]',
  		'Day'		=> '0[1-9]|[12][0-9]|3[01]',
  		'ID'		=> '[0-9]+',
  		'UUID'		=> '[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}'
  	);
		
		static public $instance = false;
		
  	static function getInstance() {
  	  if(!self::$instance) {
  	    self::$instance = new Router();
  	  }
  	  return self::$instance;
	  }
  	
  	static function connect($route, $default = array(), $params = array()) {
  		$_this = Router::getInstance();

  		if (!isset($default['action'])) {
  			$default['action'] = 'index';
  		}
  		
  		$_this->routes[] = array($route, $default, $params);
  		return $_this->routes;
	}
	
	
/**
 * Finds URL for specified action.
 *
 * Returns an URL pointing to a combination of controller and action. Param
 * $url can be:
 *
 * - Empty - the method will find adress to actuall controller/action.
 * - '/' - the method will find base URL of application.
 * - A combination of controller/action - the method will find url for it.
 *
 * @param mixed $url Cake-relative URL, like "/products/edit/92" or "/presidents/elect/4"
 *   or an array specifying any of the following: 'controller', 'action',
 *   and/or 'plugin', in addition to named arguments (keyed array elements),
 *   and standard URL arguments (indexed array elements)
 * @param mixed $full If (bool) true, the full base URL will be prepended to the result.
 *   If an array accepts the following keys
 *    - escape - used when making urls embedded in html escapes query string '&'
 *    - full - if true the full base URL will be prepended.
 * @return string Full translated URL with base path.
 * @access public
 * @static
 */
	function url($url = null, $full = false) {
		$_this = Router::getInstance();
		$defaults = $params = array('controller' => null, 'action' => 'index');
      
		if (is_bool($full)) {
			$escape = false;
		} else {
			extract(array_merge(array('escape' => false, 'full' => false), $full));
		}
    
		if (!empty($_this->__params)) {
			if (isset($this) && !isset($this->params['requested'])) {
				$params = $_this->__params[0];
			} else {
				$params = end($_this->__params);
			}
		}
		$path = array('base' => null);

		if (!empty($_this->__paths)) {
			if (isset($this) && !isset($this->params['requested'])) {
				$path = $_this->__paths[0];
			} else {
				$path = end($_this->__paths);
			}
		}
    
		$base = $path['base'];
		$extension = $output = $mapped = $q = $frag = null;

		if (((strpos($url, '://')) || (strpos($url, 'javascript:') === 0) || (strpos($url, 'mailto:') === 0)) || (!strncmp($url, '#', 1))) {
		  return $url;
		}
		if (empty($url)) {
			if (!isset($path['here'])) {
				$path['here'] = '/';
			}
			$output = $path['here'];
		} elseif (substr($url, 0, 1) === '/') {
			$output = $base . $url;
		} else {
			$output = $base . '/';
			$output .= Inflector::underscore($params['controller']) . '/' . $url;
		}
		$output = str_replace('//', '/', $output);
	
	
		if ($full && defined('FULL_BASE_URL')) {
			$output = FULL_BASE_URL . $output;
		}
		if (!empty($extension) && substr($output, -1) === '/') {
			$output = substr($output, 0, -1);
		}

		return $output . $extension . $_this->queryString($q, array(), $escape) . $frag;
	}



/**
 * Generates a well-formed querystring from $q
 *
 * @param mixed $q Query string
 * @param array $extra Extra querystring parameters.
 * @param bool $escape Whether or not to use escaped &
 * @return array
 * @access public
 * @static
 */
	function queryString($q, $extra = array(), $escape = false) {
		if (empty($q) && empty($extra)) {
			return null;
		}
	
		$join = '&';
		if ($escape === true) {
			$join = '&amp;';
		}
		$out = '';

		if (is_array($q)) {
			$q = array_merge($extra, $q);
		} else {
			$out = $q;
			$q = $extra;
		}
		$out .= http_build_query($q, null, $join);
		if (isset($out[0]) && $out[0] != '?') {
			$out = '?' . $out;
		}
		return $out;
	}
	
	
/**
 * Strip escape characters from parameter values.
 *
 * @param mixed $param Either an array, or a string
 * @return mixed Array or string escaped
 * @access public
 * @static
 */
	function stripEscape($param) {
		$_this = Router::getInstance();
		if (!is_array($param) || empty($param)) {
			if (is_bool($param)) {
				return $param;
			}

			$return = preg_replace('/^(?:[\\t ]*(?:-!)+)/', '', $param);
			return $return;
		}
		foreach ($param as $key => $value) {
			if (is_string($value)) {
				$return[$key] = preg_replace('/^(?:[\\t ]*(?:-!)+)/', '', $value);
			} else {
				foreach ($value as $array => $string) {
					$return[$key][$array] = $_this->stripEscape($string);
				}
			}
		}
		return $return;
	}

/**
 * Parses given URL and returns an array of controllers, action and parameters
 * taken from that URL.
 *
 * @param string $url URL to be parsed
 * @return array Parsed elements from URL
 * @access public
 * @static
 */
	function parse($url) {
		$_this = Router::getInstance();
		if (!$_this->__defaultsMapped) {
			$_this->__connectDefaultRoutes();
		}
		$out = array('pass' => array(), 'named' => array());
		$r = $ext = null;

		if (ini_get('magic_quotes_gpc') === '1') {
			$url = stripslashes_deep($url);
		}

		if ($url && strpos($url, '/') !== 0) {
			$url = '/' . $url;
		}
		if (strpos($url, '?') !== false) {
			$url = substr($url, 0, strpos($url, '?'));
		}
		extract($_this->__parseExtension($url));

		foreach ($_this->routes as $i => $route) {
			if (count($route) === 3) {
				$route = $_this->compile($i);
			}

			if (($r = $_this->__matchRoute($route, $url)) !== false) {
				$_this->__currentRoute[] = $route;
				list($route, $regexp, $names, $defaults, $params) = $route;
				$argOptions = array();

				if (array_key_exists('named', $params)) {
					$argOptions['named'] = $params['named'];
					unset($params['named']);
				}
				if (array_key_exists('greedy', $params)) {
					$argOptions['greedy'] = $params['greedy'];
					unset($params['greedy']);
				}
				array_shift($r);

				foreach ($names as $name) {
					$out[$name] = null;
				}
				if (is_array($defaults)) {
					foreach ($defaults as $name => $value) {
						if (preg_match('#[a-zA-Z_\-]#i', $name)) {
							$out[$name] = $value;
						} else {
							$out['pass'][] = $value;
						}
					}
				}

				foreach ($r as $key => $found) {
					if (empty($found) && $found != 0) {
						continue;
					}

					if (isset($names[$key])) {
						$out[$names[$key]] = $_this->stripEscape($found);
					} elseif (isset($names[$key]) && empty($names[$key]) && empty($out[$names[$key]])) {
						break;
					} else {
						$argOptions['context'] = array('action' => $out['action'], 'controller' => $out['controller']);
						extract($_this->getArgs($found, $argOptions));
						$out['pass'] = array_merge($out['pass'], $pass);
						$out['named'] = $named;
					}
				}

				if (isset($params['pass'])) {
					for ($j = count($params['pass']) - 1; $j > -1; $j--) {
						if (isset($out[$params['pass'][$j]])) {
							array_unshift($out['pass'], $out[$params['pass'][$j]]);
						}
					}
				}
				break;
			}
		}

		if (!empty($ext)) {
			$out['url']['ext'] = $ext;
		}
		return $out;
	}	

/**
 * Connects the default, built-in routes, including admin routes, and (deprecated) web services
 * routes.
 *
 * @return void
 * @access private
 */
	function __connectDefaultRoutes() {
		if ($this->__defaultsMapped) {
			return;
		}
		
		$this->connect('/:controller', array('action' => 'index'));
		$this->connect('/:controller/:action/*');

		if ($this->named['rules'] === false) {
			$this->connectNamed(true);
		}
		$this->__defaultsMapped = true;
	}
	
	/**
 * Parses a file extension out of a URL, if Router::parseExtensions() is enabled.
 *
 * @param string $url
 * @return array Returns an array containing the altered URL and the parsed extension.
 * @access private
 */
	function __parseExtension($url) {
		$ext = null;

		if ($this->__parseExtensions) {
			if (preg_match('/\.[0-9a-zA-Z]*$/', $url, $match) === 1) {
				$match = substr($match[0], 1);
				if (empty($this->__validExtensions)) {
					$url = substr($url, 0, strpos($url, '.' . $match));
					$ext = $match;
				} else {
					foreach ($this->__validExtensions as $name) {
						if (strcasecmp($name, $match) === 0) {
							$url = substr($url, 0, strpos($url, '.' . $name));
							$ext = $match;
						}
					}
				}
			}
			if (empty($ext)) {
				$ext = 'html';
			}
		}
		return compact('ext', 'url');
	}

/**
 * Checks to see if the given URL matches the given route
 *
 * @param array $route
 * @param string $url
 * @return mixed Boolean false on failure, otherwise array
 * @access private
 */
	function __matchRoute($route, $url) {
		list($route, $regexp, $names, $defaults) = $route;

		if (!preg_match($regexp, $url, $r)) {
			return false;
		} else {
			foreach ($defaults as $key => $val) {
				if ($key{0} === '[' && preg_match('/^\[(\w+)\]$/', $key, $header)) {
					if (isset($this->__headerMap[$header[1]])) {
						$header = $this->__headerMap[$header[1]];
					} else {
						$header = 'http_' . $header[1];
					}

					$val = (array)$val;
					$h = false;

					foreach ($val as $v) {
						if (env(strtoupper($header)) === $v) {
							$h = true;
						}
					}
					if (!$h) {
						return false;
					}
				}
			}
		}
		return $r;
	}
	
/**
 * Builds a route regular expression
 *
 * @param string $route			An empty string, or a route string "/"
 * @param array $default		NULL or an array describing the default route
 * @param array $params			An array matching the named elements in the route to regular expressions which that element should match.
 * @return array
 * @see routes
 * @access public
 * @static
 */
	function writeRoute($route, $default, $params) {
		if (empty($route) || ($route === '/')) {
			return array('/^[\/]*$/', array());
		}
		$names = array();
		$elements = explode('/', $route);

		foreach ($elements as $element) {
			if (empty($element)) {
				continue;
			}
			$q = null;
			$element = trim($element);
			$namedParam = strpos($element, ':') !== false;

			if ($namedParam && preg_match('/^:([^:]+)$/', $element, $r)) {
				if (isset($params[$r[1]])) {
					if ($r[1] != 'plugin' && array_key_exists($r[1], $default)) {
						$q = '?';
					}
					$parsed[] = '(?:/(' . $params[$r[1]] . ')' . $q . ')' . $q;
				} else {
					$parsed[] = '(?:/([^\/]+))?';
				}
				$names[] = $r[1];
			} elseif ($element === '*') {
				$parsed[] = '(?:/(.*))?';
			} else if ($namedParam && preg_match_all('/(?!\\\\):([a-z_0-9]+)/i', $element, $matches)) {
				$matchCount = count($matches[1]);

				foreach ($matches[1] as $i => $name) {
					$pos = strpos($element, ':' . $name);
					$before = substr($element, 0, $pos);
					$element = substr($element, $pos + strlen($name) + 1);
					$after = null;

					if ($i + 1 === $matchCount && $element) {
						$after = preg_quote($element);
					}

					if ($i === 0) {
						$before = '/' . $before;
					}
					$before = preg_quote($before, '#');

					if (isset($params[$name])) {
						if (isset($default[$name]) && $name != 'plugin') {
							$q = '?';
						}
						$parsed[] = '(?:' . $before . '(' . $params[$name] . ')' . $q . $after . ')' . $q;
					} else {
						$parsed[] = '(?:' . $before . '([^\/]+)' . $after . ')?';
					}
					$names[] = $name;
				}
			} else {
				$parsed[] = '/' . $element;
			}
		}
		return array('#^' . join('', $parsed) . '[\/]*$#', $names);
	}
	
/**
 * Compiles a route by numeric key and returns the compiled expression, replacing
 * the existing uncompiled route.  Do not call statically.
 *
 * @param integer $i
 * @return array Returns an array containing the compiled route
 * @access public
 */
	function compile($i) {
		$route = $this->routes[$i];

		if (!list($pattern, $names) = $this->writeRoute($route[0], $route[1], $route[2])) {
			unset($this->routes[$i]);
			return array();
		}
		$this->routes[$i] = array(
			$route[0], $pattern, $names,
			array_merge(array('plugin' => null, 'controller' => null), (array)$route[1]),
			$route[2]
		);
		return $this->routes[$i];
	}	


/**
 * Clears all routes
 *
 * @access public
 * @static
 */
 static function clearRoutes() {
   $_this = Router::getInstance();
   $_this->routes = array();
   $_this->__prefixes = array();
 }
   

/**
 * Returns the list of prefixes used in connected routes
 *
 * @return array A list of prefixes used in connected routes
 * @access public
 * @static
 */
	function prefixes() {
		$_this = Router::getInstance();
		return $_this->__prefixes;
	}
	

/**
 * Takes parameter and path information back from the Dispatcher
 *
 * @param array $params Parameters and path information
 * @return void
 * @access public
 * @static
 */
	function setRequestInfo($params) {
		$_this = Router::getInstance();
		$defaults = array('plugin' => null, 'controller' => null, 'action' => null);
		$params[0] = array_merge($defaults, (array)$params[0]);
		$params[1] = array_merge($defaults, (array)$params[1]);
		list($_this->__params[], $_this->__paths[]) = $params;

		if (count($_this->__paths)) {
			if (isset($_this->__paths[0]['namedArgs'])) {
				foreach ($_this->__paths[0]['namedArgs'] as $arg => $value) {
					$_this->named['rules'][$arg] = true;
				}
			}
		}
	}
	
}


?>