<?php

namespace Trail;

use Trail\Route;

class Collection implements \ArrayAccess, \Countable, \Iterator {

    /**
     * @property the allowed scheme options we can give 
     * @var array
     */
    protected static $schemes = ['http', 'https', 'http://', 'https://'];
    
    /**
     * @property the scheme this collection should listen to, should be either
     * http:// or https://, or actually http or https
     * @var string
     */
    protected $scheme = null;
    
    /**
     * @property the name for this collection
     * @var string
     */
    protected $name = null;
    
    /**
     * @property the namespace, can be null if directly invoked
     * @var string
     */
    protected $namespace = null;
    
    /**
     * @property the hostname this collection is listening to.
     * @var string
     */
    protected $hostname = null;
    
    /**
     * @property the target uri this collection should match
     * @var string
     */
    protected $target = null;
    
    /**
     * @property a flag to check if you want prefixed method names 
     * ( you can use get_methodName to identify the GET request )
     * @var bool
     */
    protected $prefix = false;
    
    /**
     * @property the reserved flag is used to dequeue the collection from
     * dynamic searching routes
     * @var bool
     */
    protected $reserved = false;
    
    /**
     * @property the actual routes array
     * @var array
     */
    protected $routes = [];

    /**
     * @property the current index in iterating over each route
     * @var int
     */
    protected $index = 0;

    /**
     * @property the total number of routes assigned to this collection
     * @var int
     */
    protected $total = 0;

    /**
     * @brief create a new \Trail\Collection 
     * @param array $routes
     */
    public function __construct(array $config = [], array $routes = []) {
        if( count($config) > 0){
            $this->initialize($config);
        }
        if( count($routes) > 0){
            $this->addRoutesByArray( $routes );
        }
    }
    
    /**
     * @brief initialize the collection to set up some data for the collection. 
     * @method initialize
     * public
     * @param array     $config
     * @return \Trail\Collection
     */
    public function initialize( array $config ){
        
        if( isset($config['ns'])){
            $this->namespace = $config['ns'];
        } else if( isset($config['namespace']) ){
            $this->namespace = $config['namespace'];
        }
        if( isset($config['scheme'])){
            $this->setScheme($config['scheme']);
        }
        if( isset($config['host'])){
            $this->setHostname($config['host']);
        } else if ( isset($config['hostname'])){
            $this->setHostname($config['hostname']);
        }
        if( isset($config['target'])){
            $this->setTarget($config['target']);
        }
        else if( isset($config['path'])){
            $this->setTarget($config['path']);
        }
        if( isset($config['prefix'])){
            $this->setPrefix( $config['prefix'] );
        }
        if( isset($config['routes'])){
            $this->addRoutesByArray( $config['routes'] );
        }
        if( isset($config['name'])){
            $this->setName ( $config['name'] );
        }
        if( isset($config['reserved'])){
            $this->reserved = $config['reserved'] ? true : false;
        }
        
        return $this;
    }
    
    /**
     * @brief check all the routes in the collection and see if any route matched.
     * @method verify
     * @public
     * @param   string  $uri
     * @return  bool
     */
    public function verify( $uri ){
        
        foreach( $this->routes as $route ){
            if( $route->match($uri)){
                $this->route = $route;
                return true;
            }
        }
        return false;
    }
    
    /**
     * @brief a method to check if the given uri starts with the target this collection has
     * @method matches
     * @public
     * @param   string      $uri
     * @return  bool
     */
    public function matches( $uri ){
        if( $this->hasTarget()){
            return preg_match("#^" . $this->target . "(.*)$#i", $uri);
        }
        return false;
    }
    
    /**
     * @brief returns the matched route 
     * @return type
     */
    public function getRoute(){
        return $this->route;
    }
    
    /**
     * @brief returns the namepace for this collection. 
     * @method getnamespace
     * @public
     * @return string | null
     */
    public function getNamespace(){
        return $this->namespace;
    }
    
    /**
     * @brief returns true if this collection is reserved.
     * @method isReserved
     * @public
     * @return bool
     */
    public function isReserved(){
        return $this->reserved;
    }
    
    /**
     * @brief returns true if this collection is not a reserved collection
     * @method isNotReserved
     * @public
     * @return bool
     */
    public function isNotReserved(){
        return !$this->reserved;
    }
    
    /**
     * @brief a way to check if the collection has a target to listen / match for
     * @method hasTarget
     * @public
     * @return bool
     */
    public function hasTarget() {
        return $this->target !== null && is_string($this->target) && strlen($this->target) > 0;
    }
    
    /**
     * @brief set up the target to match or listen for.
     * @method setTarget
     * @public
     * @param bool $target
     * @return \Trail\Collection
     */
    public function setTarget ( $target ) {
        if(is_string( $target )){
            $clean = '/'.trim($target, "\s\t\n\r\\\ /");
            $clean = rtrim($clean);
            $this->target = $clean;
        }
        return $this;
    }
    
    /**
     * @brief returns the target uri to match / filter against.
     * @method getTarget
     * @public
     * @return string|null
     */
    public function getTarget(){
        return $this->target;
    }
    
    /**
     * @brief set the name of the collection
     * @method setName
     * @public
     * @param   string      $name
     * @return  \Trail\Collection
     */
    public function setName ( $name ) {
        if( is_string($name)){
            $this->name = $name;
        }
        return $this;
    }
    
    /**
     * @brief eturns the name of the collection
     * @method getName
     * @public
     * @return string
     */
    public function getName(){
        return $this->name;
    }
    
    /**
     * @brief a way to check if the collection has a specific hostname to listen for
     * @method hasHostname
     * @public
     * @return bool
     */
    public function hasHostname(){
        return $this->hostname;
    }
    
    /**
     * @brief set up the hostname for the collection 
     * @method setHostname
     * @public
     * @param bool $hostname
     * @return \Trail\Collection
     */
    public function setHostname( $hostname ) {
        $this->hostname = $hostname;
        return $this;
    }
    
    /**
     * @brief returns the hostname
     * @method getHostname
     * @public
     * @return  string
     */
    public function getHostname() {
        return $this->hostname;
    }
    
    /**
     * @brief a way to check if the collection allows prefixed method names to be
     * used and matched. Defaults to false, must be set to true.
     * @method hasScheme
     * @public
     * @return bool
     */
    public function hasScheme(){
        return $this->scheme;
    }
    
    /**
     * @brief set up the scheme to use in this collection, should either be
     * http:// or https:// or to be specific, http or https
     * @method setScheme
     * @public
     * @param bool $scheme
     * @return \Trail\Collection
     */
    public function setScheme( $scheme ) {
        if( in_array($scheme, self::$schemes)){
            if(strpos( $scheme, 's')){
                $this->scheme = 'https';
            } else {
                $this->scheme = 'http';
            }
        }    
        return $this;
    }
    
    /**
     * @brief returns the scheme used for this controller, either is http or https
     * or null by default ( defaults to the current request uri )
     * @method getScheme
     * @public
     * @return  string | null  
     */
    public function getScheme(){
        return $this->scheme;
    }
    
    /**
     * @brief validate the given scheme ( or defaults to the Request::scheme )
     * if however, you never specified a scheme, it will return true by default. 
     * @method validateScheme
     * @public
     * @param   string|null $scheme
     * @return  boolean
     */
    public function validateScheme ( $scheme = null ){
        
        if( $this->scheme === null ){
            return true;
        }
        
        if ( $scheme == null ){
            $scheme = \Trail\Request::request('method');
        }
        return $this->scheme === $scheme;
    }
    
    /**
     * @brief a way to check if the collection allows prefixed method names to be
     * used and matched. Defaults to false, must be set to true.
     * @method hasPrefix
     * @public
     * @return bool
     */
    public function hasPrefix(){
        return $this->prefix;
    }
    
    /**
     * @brief set up prefixing. 
     * @method setPrefix
     * @public
     * @param bool $prefix
     * @return \Trail\Collection
     */
    public function setPrefix( $prefix ) {
        $this->prefix = $prefix ? true : false;
        return $this;
    }
    
    /**
     * @brief returns true if this collection wants it`s methods prefixed. false if not
     * @method getPrefix
     * @public
     * @return  bool
     */
    public function getPrefix() {
        return $this->prefix;
    }
    
    /**
     * @brief add an array of objects to the collection. Make sure you 
     * specify as what kind of models you want them accessed.
     * @method addRoutesByArray
     * @public
     * @param array $routes
     * @return \Trail\Collection
     */
    public function addRoutesByArray(array $routes) {
        foreach ($routes as $route) {
            if( ! $route instanceof \Trail\Route){
                $route = new \Trail\Route($route);
            }
            $this->add( $route );
            
            $route->setCollection( $this->getName());
        }
        return $this;
    }
    
    /**
     * @brief add a route the the collection
     * @method addRoute
     * @public
     * @alias offsetSet
     * @param type $key
     * @param type $value
     */
    public function add ( \Trail\Route $route ) {
        return $this->offsetSet ( $route );
    }
    
    /**
     * @alias offsetUnset
     * @param {mixed} offset
     * @returns {void}
     */
    public function remove($key) {
        
        if( $key instanceof \Trail\Route ){
            foreach($this->routes as $i => $route){
                if( $route == $key){
                    unset($this->routes[$i]);
                }
            }
        }
        else {
            return $this->offsetUnset($key);
        }
    }
    
    /**
     * @alias offsetExists
     * @param type $key
     * @return type
     */
    public function has($key) {
        return $this->offsetExists($key);
    }
    
    /**
     * @brief returns a given value based on the key given. 
     * @method get
     * @public
     * @param string|null $key
     * @return \Trail\Collection
     */
    public function get($key = null) {
        if ($key == null) {
            return $this->all();
        }
        return $this->offsetGet( $key );
    }

    /**
     * @brief Returns all the elements within the collection as a normal array. 
     * @method all
     * @public
     * @return {mixed|null} 
     */
    public function all() {
        return $this->routes;
    }
    
    /**
     * @brief returns the first element and decreases the collection by 1, 
     * thus removing itself for the collection.
     * @method first
     * @public
     * @return \Trail\Route
     */
    public function first() {
        return array_shift($this->routes);
    }
    
    /**
     * @brief returns the last element found in the data container.
     * @method last
     * @public
     * @return \Trail\Route
     */
    public function last () {
        return array_pop($this->routes);
    }
    
    /**
     * Returns the associated value with the given key. The key can be anything
     * obviously, but it does check if the given key exists. 
     * 
     * @param {mixed} offset
     * @returns {mixed|bool} the associated value or false if not found
     */
    public function offsetGet($key) {
        return isset($this->routes [$key]) ? $this->routes [$key] : false;
    }

    /**
     * Overriding the default magical __get behaviour by returning
     * what the default behaviour is for the normal get() function.
     * 
     * @see offsetGet
     * @param {string|mixed} key 
     * @return {mixed}
     */
    public function __get($key) {
        return $this->offsetGet($key);
    }

    /**
     * @brief Returns the number of elements inside the collection object.
     * @method count
     * @public
     * @param {string} $mode
     * @return {int} length
     */
    public function count($mode = 'COUNT_NORMAL') {
        switch ($mode) {
            default: return $this->total;
        }
    }

    /**
     * @brief returns the internal pointer`s Model associated.
     * @method current
     * @public
     * @return {\packages\orm\Model}
     */
    public function current() {
        return $this->routes[$this->index];
    }

    /**
     * @brief returns the internal pointer`s index.
     * @method key
     * @public
     * @return {int}
     */
    public function key() {
        return $this->index;
    }

    /**
     * @brief increments the internal pointer by 1. 
     * @method next
     * @public
     * @return {\packages\orm\Collection}
     */
    public function next() {
        $this->index++;
    }

    /**
     * @brief reset the internal pointer to zero. 
     * @method rewind
     * @public
     * @return {\packages\orm\Collection}
     */
    public function rewind() {
        $this->index = 0;
    }

    /**
     * @brief returns true if the current index has a valid value associated
     * @method valid
     * @public
     * @return {bool}
     */
    public function valid() {
        if ($this->index < $this->total) {
            return $this->offsetExists($this->index);
        }
        return false;
    }
    
    /**
     * Set the associated offset with the given value.
     * 
     * @param {mixed} $key
     * @param {mixed} value
     * @returns {void}
     */
    public function offsetSet($key, $val = null) {
        // we can`t override default behaviour,  however, we can implement
        // our own typechecking :P
        if ($val === null) {
            $val = $key;
            $key = $this->count();
        }
        if ($val instanceof \Trail\Route) {
            $this->total++;
            $this->routes [] = $val;
        }
        return $this;
    }

    /**
     * Unset a given value with the given offset.
     * 
     * @param string offset
     * @returns \Trail\Collection
     */
    public function offsetUnset( $offset ) {
        unset($this->routes[ $offset ]);
        return $this;
    }

    /**
     * Check if a given offset exists. 
     * 
     * @param {mixed} offset
     * @returns {void}
     */
    public function offsetExists($key, $field = null) {
        if (isset($this->routes[$key])) {
            return isset($this->routes[$key]);
        } else {
            foreach ($this->routes as $item) {
                if ($item->$field == $key) {
                    return true;
                }
            }
        }
        return false;
    }
}
