<?php

namespace Trail;

use \Trail\Router;

class Route {

    /**
     * @property all the methods we can match against
     * @var array
     */
    private $methods = [];

    /**
     * @property the uri we`re matching
     * @var string
     */
    private $uri = null;

    /**
     * @property the regex we`re gonna try
     * @var string
     */
    private $regex_uri = null;

    /**
     * @property the full uri string to analyse
     * @var string
     */
    private $prepared_uri = null;

    /**
     * @property the indices for the regex positions
     * @var array
     */
    private $regex_positions = null;

    /**
     * @property the callback, can either be a string or a callback function
     * @var string|Callable
     */
    private $callback = null;

    /**
     * @property the parameters clause
     * @var array
     */
    private $parameters = [];

    /**
     * @property the name of the collection this route is part of
     * @var string
     */
    private $collection = null;

    /**
     * @property a flag to check if this route got matched
     * @var bool
     */
    private $matched = false;

    /**
     * @property the namespace strings we managed to filter out of the 
     * given namespace supplied.
     * @var array
     */
    private $namespace = [];

    /**
     * @property the name of this route, there`s a named_route parameter,
     * but it hsould be removed and replaced by this field
     * @var string
     */
    private $name = null;

    /**
     * The config array we initialized it with
     * @param array $config
     */
    public function __construct($config = null) {
        if ($config !== null && is_array($config)) {
            $this->initialize($config);
        }
    }

    /*
     * Fluent functionality
     */

    /**
     * @brief set up the name for this route.
     * @method name
     * @public
     * @param   string          $name
     * @return  \Trail\Route
     */
    public function name($name) {

        // in case of a named route, we need to prepare the regex uri string 
        // either way.. :(
        \Trail\Routes::add($name, $this);
        $this->name = $name;
        return $this;
    }

    /**
     * @brief give the route a collection`s name. 
     * @method collection
     * @public
     * @param   string          $name
     * @return  \Trail\Route
     */
    public function collection($name) {
        $router = \Trail\Router::instance();

        // first, remove it from the current collection ( if any )
        if ($this->collection !== null) {
            $current_collection = $router->getCollection($this->collection);
            
            if( $current_collection){
                $current_collection->remove($this);
            }
        }

        // this is dangerous in the sense that a collection may not exist yet
        // and therefore doesn`t have any defaults set. This is no problem per
        // se, the problem 
        if (!$router->hasCollection($name)) {
            $router->addCollection(new \Trail\Collection(['name' => $name]));
        } 
        
        $router->getCollection( $name )->add($this);
        $this->collection = $name;
        return $this;
    }

    public function group($name) {
        return $this->collection($name);
    }

    /**
     * @brief match the given $uri against this route`s specified uri.
     * @method match
     * @public
     * @param   string  $uri
     * @return \Trail\Route
     */
    public function match($uri) {
        if (!in_array(\Trail\Request::request('method'), $this->methods)) {
            return false;
        }
        try {
            $this->prepare();
            $matches = [];
            
            if (preg_match_all($this->regex_uri, $uri, $matches)) {
                return $this->handleMatchedRoute($uri);
            }
        } catch (\Exception $e) {
            \Trail\Router::instance()->error('
                We tried retrieving the target URI from a parent collection but 
                all we got was <b>boolean</b>. It would seem the parent collection 
                does not exist. So this route is part of non-existent collection.');
            \Trail\Router::instance()->error($e->getMessage());
            return false;
        }
        return false;
    }

    /**
     * @brief handle the logic once we matched this route as the one speicified
     * by the uri given.
     * @method handleMatchedRoute
     * @private
     * @param   string  $uri
     * @return  boolean
     */
    private function handleMatchedRoute($uri) {
        $collection = \Trail\Router::instance()->getCollection($this->collection);
        if ($collection !== null) {
            $this->applyNamespace($collection->getNamespace(), $collection->getPrefix());
        }
        $this->matched = true;
        $arguments = explode('/', trim($uri, '/'));
        foreach ($this->regex_positions as $key => $indice) {
            $this->arguments[$indice] = $arguments[$key];
        }
        return true;
    }

    /**
     * @brief prepare the regex uri to perform and match. 
     * @method prepare
     * @protected
     * @param   string  $identifier
     * @return  void
     */
    protected function prepare($identifier = ':') {

        $args = explode('/', $this->getPreparedUri());
        $final = [];
        $this->regex_positions = [];

        foreach ($args as $key => $val) {
            if (($pos = strpos($val, $identifier)) !== false) {
                if (isset($this->parameters[$val])) {
                    $regex = $this->parameters[$val];
                } else {
                    $regex = \Trail\Router::$regex['alpha-numeric'];
                }
                $this->regex_positions[$key] = substr($val, $pos + 1);
                $final[] = $regex;
            } else {
                $final[] = $val;
            }
        }

        $this->regex_uri = '@^/' . implode('/', $final) . '$@';
    }

    /**
     * @brief build up the complete query string to analyse. 
     * @method buildCompleteQueryString
     * @public
     * @return void
     */
    public function buildCompleteQueryString() {
        $collection = \Trail\Router::instance()
                ->getCollection($this->collection);
        $target = $collection->getTarget();
        $this->uri = $target . $this->uri;
        $this->uri = str_replace('//', '/', $this->uri);
        $this->prepared_uri = rtrim(trim($this->uri, '/'), '/');
    }

    /**
     * @brief the bootstrap array, make sure it has a pre-defined array
     * where the first argument should be either a string or an array of strings
     * specifying the request methods it can match against. 
     * The second parameter is the uri we`re using.
     * the third parameter can either be a callback or a string to use for other
     * bootstrap functionality. 
     * The fourth argument should be an array where you define your parameters,
     * if you use clauses and don`t provide parameter patterns, a default pattern
     * will be used. 
     * @method initialize
     * @public
     * @param array $config
     * @return \Trail\Route
     */
    public function initialize(array $config) {

        $this->validateMethods($config[0]);
        $this->uri = $config[1];
        $this->callback = $config[2];
        $this->parameters = (isset($config[3]) ? $config[3] : []);
        $this->collection = (isset($config[4]) ? $config[4] : 'default');

        if (isset($this->parameters['name'])) {
            $this->name($this->parameters['name']);
        }

        if (isset($this->parameters['as'])) {
            $this->name($this->parameters['as']);
        }
    }

    /**
     * @brief validate if the given method(s) are allowed to be used. 
     * @method validatemethods
     * @public
     * @param   string|array $methods
     * @return  \Trail\Route
     */
    public function validateMethods($methods) {
        if (is_array($methods)) {
            foreach ($methods as $method) {
                if (in_array($method, \Trail\Router::$allowed['methods'])) {
                    $this->methods[] = $method;
                }
            }
        } else if ($methods == 'all') {
            $this->methods = ['get', 'post', 'put', 'patch', 'delete'];
        } else if (is_string($methods)) {
            if (in_array($methods, \Trail\Router::$allowed['methods'])) {
                $this->methods[] = $methods;
            }
        }
        return $this;
    }

    /**
     * @brief returns this route as a normal url, this way you can generate
     * the url for this route. If there are named parameters inside the uri,
     * you can provide an array containing the values for those parameters.
     * @method url
     * @public
     * @param   array|string $arguments
     * @return  string
     */
    public function url( $arguments = [] ) {
        
        if(is_string($arguments)){
            $arguments = [$arguments];
        }
        $identifier = ':';
        $segments = explode('/', trim($this->getPreparedUri(), '/'));
        $link = [];
        $count = 0;
        foreach ($segments as $segment) {
            $var = null;
            if (strpos($segment, $identifier) !== false) {
                $cleansed = str_replace($identifier, '', $segment);
                if (isset($arguments[$cleansed])) {
                    $link[] = $arguments[$cleansed];
                } else {
                    $link[] = (isset($arguments[$count]) ? $arguments[$count] : '');
                }
                $count++;
            } else {
                $link[] = $segment;
            }
        }

        return '/' . implode('/', $link);
    }

    /**
     * @brief apply the collection`s given namespace to the route
     * @method applyNamespace
     * @public
     * @param   string  $namespace
     * @return  \Trail\Route
     */
    public function applyNamespace($namespace, $prefixed = false) {
        if (is_string($this->callback)) {
            $this->callback = str_replace(['{ns}', '{namespace}'], $namespace, $this->callback);
            list($ns, $action ) = explode('@', $this->callback);

            if ($prefixed === true) {
                $action = \Trail\Request::request('method') . '_' . $action;
            }

            $this->namespace['namespace'] = $ns;
            $this->namespace['action'] = $action;
        }
        return $this;
    }

    /**
     * @brief set up the name of the collection
     * @method setCollection
     * @public
     * @param string $name
     * @return \Trail\Route
     */
    public function setCollection($name) {
        $this->collection = $name;
    }

    /**
     * @brief returns the parameterized arguments in the query string. 
     * @method getArguments
     * @public
     * @return  array
     */
    public function getArguments() {
        return $this->arguments;
    }

    /**
     * @brief returns the callback
     * @method getCallback
     * @public
     * @return \Callable|string
     */
    public function getCallback() {
        return $this->callback;
    }

    /**
     * @brief returns the prepared query string based on the parent collection
     * @method getPreparedUri
     * @public
     * @return  string
     */
    public function getPreparedUri() {
        if ($this->prepared_uri === null) {
            $this->buildCompleteQueryString();
        }
        return $this->prepared_uri;
    }

    /**
     * @brief returns the name of this route.
     * @method getName
     * @public
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @brief returns the namespace given to this route. If the route has an 
     * annonymous callback function, this will return null.
     * @method getNamespace
     * @public
     * @return  string|null
     */
    public function getNamespace() {
        if (isset($this->namespace[0])) {
            return $this->namespace[0];
        }
        return null;
    }

}
