<?php

namespace Trail;

use \Trail\Route;
use \Trail\Collection;

/**
 * @class Router
 * @author Merijn Venema
 * @access public
 */
class Router {

    /**
     * the hostname of the current domain
     * @var string
     */
    protected $hostname = null;

    /**
     * the uri request stripped of query parameters
     * @var string
     */
    protected $uri = null;

    /**
     * all the collections
     * @var array
     */
    protected $collections = [];

    /**
     * @property a list of the reserved collection names
     * @var array
     */
    protected $reserved_collections = ['default', 'error'];

    /**
     * @property all the regex combinations, the default ones anyway
     * @var array
     */
    public static $regex = [
        'alpha-numeric' => '[\w\d\-]{1,64}',
        'alpha' => '[a-zA-Z\-\_]{1,64}',
        'numeric' => '[0-9]{1,24}'
    ];

    /**
     * @property the name of the collection that had a match
     * @var string
     */
    protected $current_collection = null;

    /**
     * The route we matched 
     * @var \Trail\Route
     */
    protected $current = null;

    /**
     * @property the errors array contains any and all errors we found/reported
     * @var array
     */
    protected $errors = [];

    /**
     * @property a static list of methods we allow to call magically
     * @var array
     */
    public static $allowed = [
        'methods' => ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'],
    ];

    /**
     * @brief the magic __call method to use dynamic allocated routes; the options
     * are either get, post, put, patch, delete, head and options. 
     * @method __call
     * @public
     * @param string    $method_name
     * @param array     $arguments
     * @return \Trail\Router
     */
    public function __call($method_name, $arguments) {
        $name = strtolower($method_name);
        array_unshift($arguments, $name);
        
        if(method_exists( $this, '_' . $method_name)){
            return call_user_func_array([$this, '_' . $method_name], $arguments);
        }

        if (in_array($name, self::$allowed['methods'])) {
            return call_user_func_array([self::instance(), 'method'], $arguments);
        }
    }

    /**
     * @brief the magic __call method to use dynamic allocated routes; the options
     * are either get, post, put, patch, delete, head and options. 
     * @method __callStatic
     * @public
     * @param string    $method_name
     * @param array     $arguments
     * @return \Trail\Router
     */
    public static function __callStatic($method_name, $arguments) {
        $name = strtolower($method_name);
        array_unshift($arguments, $name);
        
        if(method_exists( self::instance(), '_' . $method_name)){
            return call_user_func_array([self::instance(), '_' . $method_name], $arguments);
        }

        if (in_array($name, self::$allowed['methods'])) {
            return call_user_func_array([self::instance(), 'method'], $arguments);
        }
    }

    /**
     * @brief check the request uri and start matching it against the collections.
     * If no match was found, use the default collection. Once a collection has
     * been set, start matching individual routes.
     * @method verify
     * @public
     * @return bool
     */
    protected function _verify() {

        foreach ($this->collections as $collection) {
            if ($collection->isNotReserved() && $collection->validateScheme() &&  $collection->matches($this->uri)) {
                $this->current_collection = $collection->getName();
            }
        }

        if ($this->current_collection === null) {
            $this->current_collection = 'default';
        }

        if (!$this->hasCollection($this->current_collection)) {
            $this->current_collection = "error";
        }
        
        if ($this->collections[$this->current_collection]->verify($this->uri)) {
            $this->current = $this->collections[$this->current_collection]->getRoute();
        }

        // @todo: Merijn add logic to fix and/ or return error code or the error
        // collection`s default handler

        if ($this->hasMatched()) {
            if (is_callable($this->current->getCallback())) {
                return call_user_func_array($this->current->getCallback(), $this->current->getArguments());
            }
            return true;
        }

        return false;
    }

    /**
     * @brief if there was a match, that route should be stored on the current field
     * therefore, we can return it. 
     * @method  getRoute
     * @public
     * @return  bool
     */
    public function getRoute() {
        if ($this->current !== null) {
            return $this->current;
        }
        return false;
    }

    /**
     * @brief a helper to check if we have a matched route 
     * @method hasMatched
     * @public
     * @return  bool
     */
    public function hasMatched() {
        return $this->current !== null;
    }

    /**
     * @brief returns a collection if we have a collection with that name
     * @method  getCollection
     * @public
     * @param   string      $name
     * @return  \Trail\Collection
     */
    public function getCollection($name = null, $fallback = 'default') {
        if ($this->hasCollection($name)) {
            return $this->collections[$name];
        } else if ($this->hasCollection($fallback)) {
            return $this->collections[$fallback];
        }
        return null;
    }

    /**
     * @brief add a error message to the router instance. That way, this library
     * has one method to notify and report problems. 
     * @method error
     * @public
     * @param   string      $message
     * @param   string|int  $line
     * @return \Trail\Router
     */
    public function error($message, $line = null, $file = null) {
        $this->errors[] = ['message' => $message, 'line' => $line, 'file' => $file];
        return $this;
    }

    /**
     * @brief a way to help check and see if there are any errors
     * @method hasErrors
     * @public
     * @return bool
     */
    public function hasErrors() {
        return count($this->errors);
    }

    /**
     * @brief a way to retrieve the error array from the router
     * @method getErrors
     * @public
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * @brief validate the default collections that are reserved. 
     */
    public function verifyDefaultCollections() {
        // check if a normal route exists..
        $this->current_collection = 'default';
        $results = $this->collections['default']->verify($this->uri);

        if (!$results) {
            // @todo add some functionality to handle other reserved collections
            // beside the default and error collection.
            $this->current_collection = 'error';
        }
    }

    /**
     * @brief add a route to the router, add it by collection or just default. 
     * It accepts either an array containing all the data
     * @method method
     * @public
     * @param string            $request
     * @param string            $uri
     * @param string|Callable   $callback
     * @param array             $parameters
     * @param string            $collection
     * @return \Trail\Router
     */
    public function method($request, $uri, $callback, $parameters = [], $collection = null) {

//        if (is_array($request)) {
//            return $this->method(
//                            $request[0], 
//                    $request[1], $request[2], isset($request[3]) ? $request[3] : [], isset($request[4]) ? $request[4] : null);
//        } else {

        if (!is_array($parameters)) {
            if ($parameters !== null) {
                $collection = $parameters;
            }
            $parameters = [];
        }

        if ($collection === null) {
            $collection = 'default';
        }

        if (!$this->hasCollection($collection)) {
            $this->addCollection(new Collection(['name' => $collection]));
        }

        $route = new \Trail\Route([$request, $uri, $callback, $parameters, $collection]);
        $this->addRouteToCollection($route, $collection);

        // }
        return $route;
    }

    /**
     * @brief add a Route object to the collection given. Be aware that the $collection
     * variable should be a string. 
     * @method addRouteToCollection
     * @public
     * @param   \Trail\Route    $route
     * @param   string          $collection
     * @return  \Trail\Router
     */
    public function addRouteToCollection(\Trail\Route $route, string $collection) {
        if ($this->hasCollection($collection)) {
            $this->collections[$collection]->add($route);
        }
        return $this;
    }

    /**
     * @brief add a route object to a given collection if you choose to specify one. 
     * If you ommit the $collection parameter, the route will be added to the
     * default collection. 
     * @method addRoute
     * @public
     * @param Route $route
     * @param string $collection
     * @return \Trail\Router
     */
    public function addRoute(\Trail\Route $route, $collection = null) {
        if ($collection === null) {
            $collection = 'default';
        }
        $this->collections[$collection]->addRoute($route);
        return $this;
    }

    /**
     * @brief add routes by array. Pass along a second, optional, parameter to 
     * insert the routes in a collection of your own choosing. 
     * @method addRoutesByArray
     * @public
     * @param array     $routes
     * @param string    $collection
     * @return \Trail\Router
     */
    public function addRoutesByArray(array $routes, $collection = null) {
        if ($collection === null) {
            $collection = 'default';
        }
        $this->collections[$collection]->addRoutesByArray($routes);
        return $this;
    }

    /**
     * @brief dd a collection to the router
     * @method addCollection
     * @public
     * @param \Trail\Collection $collection
     * @return \Trail\Router
     */
    public function addCollection(\Trail\Collection $collection) {
        $this->collections[$collection->getname()] = $collection;
        return $this;
    }

    /**
     * @brief Add a collection by array. This expects an array that contains
     * information for only 1 collection!
     * @method addCollectionByArray
     * @public
     * @param \Trail\Collection $collection
     * @return \Trail\Router
     */
    public function addCollectionByArray($name, array $collection) {        
        
        $config = isset($collection['config']) ? $collection['config'] : [];
        $routes = isset($collection['routes']) ? $collection['routes'] : null;
        $name =  isset($collection['name']) ? $collection['name'] : $name;
        
        $config['name'] = $name;
        
        if (!$this->hasCollection($name)) {
            // create and add a new collection to the router
            $this->collections[$name] = new \Trail\Collection();
        }

        $this->collections[$name]->initialize($config);
        $this->collections[$name]->setname($name);

        if ( $routes !== null ) {
            $this->collections[$name]->addRoutesByArray($routes);
        }
        return $this;
    }

    /**
     * @brief add an array of collections by using the "$route" template. 
     * @method addMultipleCollectionsByArray
     * @public
     * @param array $collections
     * @return \Trail\Router
     */
    public function addMultipleCollectionsByArray(array $collections) {

        foreach ($collections as $name => $collection) {
            $this->addCollectionByArray($name, $collection);
        }

        return $this;
    }

    /**
     * @brief a helper to check if there exists a collection with the name given.
     * @method hasCollection
     * @public
     * @param string $name
     * @return bool
     */
    public function hasCollection($name) {
        return isset($this->collections[$name]) && $this->collections[$name] instanceof \Trail\Collection;
    }

    /**
     * @property the singleton instance to carry 
     * @var \Trail\Router
     */
    protected static $singleton = null;

    private function __construct() {
        $this->request = \Trail\Request::getInstance();
        $this->uri = $this->request->clean('uri');

        $this->collections['default'] = new Collection([
            'reserved' => true
        ]);
        $this->collections['error'] = new Collection([
            'reserved' => true
        ]);
    }

    /**
     * @brief returns the \Trail\Router singleton instance. 
     * @method instance
     * @public
     * @return \Trail\Router
     */
    public static function instance() {
        if (!self::$singleton instanceof \Trail\Router) {
            self::$singleton = new static();
        }
        return self::$singleton;
    }

}
