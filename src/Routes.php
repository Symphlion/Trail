<?php

namespace Trail;


class Routes {
    
    /**
     * @property a link to the singleton Router instance
     * @var \Trail\Router
     */
    protected static $router = null;
    
    /**
     * @property a link to the singleton Request instance
     * @var \Trail\Request
     */
    protected static $request = null;
    
    /**
     * @property the named routes array contains all the named routes including
     * the corresponding regex uri 
     * @var []
     */
    protected static $named_routes = [];
    
    /**
     * @property all kinds of methods we`re allowed to call.
     * @var array
     */
    private static $allowed = [
        'methods' => ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'],
        'helpers' => ['current', 'scheme', 'add', 'remove', 'validate', 'has', 'get', 'url']
    ];
    
    /**
     * @brief a helper to check if a named route with the given name exists.
     * @method hasRoute
     * @public
     * @param   string  $name
     * @return  bool
     */
    protected function _has($name){
        return isset(self::$named_routes[$name]);
    }
    
    
    /**
     * @brief a helper to check if a named route with the given name exists.
     * @method _url
     * @protected
     * @param   string          $name
     * @param   array|string    $arguments
     * @return  string|bool     the final url string or false on failure
     */
    protected function _url($name, $arguments = [], $fallback = '/'){
        
        // if the named route is not set, return fallback by default
        if( ! $this->_has($name)){
            return $fallback;
        }
        
        // make sure we have a working array
        if( is_string($arguments)){
            $arguments  = [ $arguments ];
        }
        
        // return the stringified url 
        return self::$named_routes[ $name ]->url($arguments, $fallback);
    }
    
    /**
     * @brief add a named route to the Routes table
     * @method add
     * @public
     * @param   string  $name
     * @param   string  $route
     * @return  \Trail\Routes
     */
    protected function _add($name, \Trail\Route $route){
        self::$named_routes[$name] = &$route;
        return $this;
    }
    
    /**
     * @brief the magic __call method allows us to call certain private and protected
     * methods statically as well as dynamically. For the full list of methods, 
     * see the static $allowed array.
     * @method __call
     * @param   string  $name
     * @param   array   $arguments
     * @return mixed
     */
    public function __call( $name, $arguments ){
        if(method_exists($this, '_' . $name)){
            return call_user_func_array([$this, '_' . $name ], $arguments);
        }
        
        if( in_array($name, self::$allowed['methods'])){
            return self::$router->$name($arguments);
        }
        
        if( in_array($name, self::$allowed['helpers'])){
            return call_user_func_array([$this, '_' . $name], $arguments);
        }
        
        if (substr($name, 0, 3) == 'get') {
            $field = substr($name, 3);
            $snake_case = \Trail\Request::toSnakeCase ( $field );
            return $this->$snake_case;
        }
    }
    
    /**
     * @brief the magic __call method allows us to call certain private and protected
     * methods statically as well as dynamically. For the full list of methods, 
     * see the static $allowed array.
     * @method __call
     * @param   string  $name
     * @param   array   $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments ){
        
        if(method_exists(self::instance(), '_' . $name)){
            return call_user_func_array([ self::instance(), '_' . $name ], $arguments);
        }
        
        if( in_array($name, self::$allowed['methods'])){
            $uri = $arguments[0];
            $callback = $arguments[1];
            $parameters = isset( $arguments[2]) ? $arguments[3] : [];
            $collection = isset( $arguments[3] ) ? $arguments[3] : null;
            self::$router->method($name, $uri, $callback, $parameters, $collection);
        }
        
        if( in_array($name, self::$allowed['helpers'])){
            return call_user_func_array([self::instance(), '_' . $name], $arguments);
        }
        
        if (substr($name, 0, 3) == 'get') {
            $field = substr($name, 3);
            $snake_case = \Trail\Request::toSnakeCase ( $field );
            return self::instance()->$snake_case;
        }
    }
    
    /**
     * @property the singleton instance of this Routes class
     * @var \Trail\Routes
     */
    private static $singleton = null;
    
    /**
     * @brief as we don`t need any loading or setting, we`re just using an empty 
     * constructor, we don`t need any other stuff..
     */
    private function __construct(){
        self::$request = \Trail\Request::getInstance();
        self::$router = \Trail\Router::instance();
    }
    
    /**
     * @brief to get this instance, retrieve it with this method.@
     * @method instance
     * @public
     * @static
     * @return \Trail\Routes
     */
    public static function instance(){
        if( ! self::$singleton instanceof self ){
            self::$singleton = new self();
        }
        return self::$singleton;
    }
}