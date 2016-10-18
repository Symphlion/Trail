<?php

namespace Trail;

class Request implements \ArrayAccess {

    /**
     * @property store the data we queried 
     * @var array
     */
    protected $data = [];

    /**
     * @property the query parameter string ( i.e. the part after the ? )
     * @var string
     */
    protected $query_string = null;
    
    /**
     * @property the query arguments found in the query string as an array
     * @var array
     */
    protected $query_arguments = null;
    
    /**
     * @property the clean uri part of the request uri
     * @var string
     */
    protected $clean_uri = null;
    
    /**
     * @property all the magic methods and fields we allow
     * @var array
     */
    protected static $allowed = [
        'methods' => ['get', 'http', 'request', '']
    ];
    
    private function __construct() {}

    
    /**
     * @brief returns the query parameters found in the query string
     * @method _getQueryParameters
     * @protected
     * @return array|null
     */
    protected function _getQueryParameters( $mask = '?', $glue = '&', $assignment = '='){
        
        if( is_array( $this->query_arguments)){
            return $this->query_arguments;
        }
        
        if ( $this->_hasQueryString($mask) ){
            // explode on the & sign
            $intermediary = explode($glue, $this->query_string);
            // reset the query_arguments back to an empty array
            $this->query_arguments = [];
            foreach($intermediary as $set){
                list($key, $value) = explode($assignment, $set);
                $this->query_arguments[$key] = $value;
            }   
        }
        return $this->query_arguments;
    }
    
    /**
     * @brief returns true if the $query_arguments array is set and counts more then 0
     * elements.
     * @method _hasQueryParameters
     * @protected
     * @return bool
     */
    protected function _hasQueryParameters(){
        return is_array($this->query_arguments) and count($this->query_arguments) > 0;
    }
    
    /**
     * @brief a way to check if we have a query string 
     * @method _hasQueryString
     * @protected
     * @return bool
     */
    protected function _hasQueryString($mask = '?'){
        if( $this->query_string !== null ){
            return strlen($this->query_string) > 0;
        } else {
            return $this->_getQueryString($mask) ? true : false;
        }
    }
    
    /**
     * @brief the way to create a query_string based on the request uri given. 
     * @method _getQueryString
     * @protected
     * @return string|null
     */
    protected function _getQueryString( $mask = '?'){
        if( $this->query_string !== null ){
            return $this->query_string;
        }
        if(($index = strpos( $this->request_uri, $mask))){
            $this->query_string = substr( $this->request_uri, $index + 1);
        }
        return $this->query_string;
    }
    
    protected function _clean( $field ){
        if( is_array($field)){
            $field = array_shift($field);
        }
        switch($field){
            case 'uri': case 'request-uri':
                
                if( $this->clean_uri !== null ){
                    return $this->clean_uri;
                }
                
                $uri = $this->request_uri;
                if( ( $pos = strpos($uri, '?'))){
                    $uri = substr($uri, 0, $pos);
                } 
                $this->clean_uri = trim( $uri );
                return $this->clean_uri;
        }
    }
    
    /**
     * @brief set up the key value pair in the data array
     * @method set
     * @public
     * @param   string          $name
     * @param   string|mixed    $val  
     * @return  \Trail\Request
     */
    public function offsetSet( $name, $val ){
        $key = str_repeat('_', '-', strtolower( $name ));
        $this->data[ $key ] = $val;
        return $this;
    }
    
    /**
     * @brief a shortcut and non-instanced way to get the value by using it as a 
     * get method defined. 
     * @method get
     * @public
     * @param   string  $name
     * @return  string
     */
    public function offsetGet($name){
        return $this->$name;
    }
    
    /**
     * @brief a way to check if a given offset/key exists.
     * @method offsetExists
     * @public
     * @param   string $name
     * @return  bool
     */
    public function offsetExists($offset) {
        return isset( $this->data[$offset] ) and $this->data[$offset] !== null;
    }

    /**
     * @brief remove a key from the internal data array
     * @method offsetUnset
     * @public
     * @param   string  $offset
     * @return  \Trail\Request
     */
    public function offsetUnset($offset) {
        if( $this->offsetExists($offset)){
            $this->data[$offset] = null;
            unset($this->data[$offset]);
        }
        return $this;
    }

    /**
     * @brief get the HTTP value found in the request. 
     * these are the options you can ask for:
     *   cache-control, upgrade-insecure-requests, connection, dnt, 
     *   accept-encoding, accept-language, accept, user-agent, host
     * @method http
     * @protected
     * @param string $type
     * @return string
     */
    protected function _http($type) {
        
        $verb = 'http_' . str_replace('-', '_', $type);

        if ( isset($this->data[$verb]) and $this->data[$verb] !== null) {
            return $this->data[$verb];
        }

        $this->data[$verb] = filter_input(INPUT_SERVER, strtoupper($verb));
        return $this->$verb;
    }
    
    /**
     * @brief a protected method to help retrieve the data from the REQUEST body.
     * @method _get
     * @protected
     * @param string $name
     * @return string
     */
    protected function _get( $name ){
        
        $verb = str_replace('-', '_', strtolower($name));
        if (isset($this->data[$verb]) and $this->data[$verb] !== null) {
            return $this->data[$verb];
        }
        $this->data[$verb] = filter_input(INPUT_SERVER, strtoupper($verb));
        return $this->data[$verb];
    }
    
    /**
     * @brief get the Request value found in the request. 
     * these are the options you can ask for:
     *   uri, scheme, method, time-float, time
     * @method _request
     * @protected
     * @param string $type
     * @return string
     */
    protected function _request ($type) {
        
        $verb = 'request_' . str_replace('-', '_', $type);

        if ( isset($this->data[$verb]) and $this->data[$verb] !== null) {
            return $this->data[$verb];
        }

        $this->data[$verb] = strtolower( filter_input(INPUT_SERVER, strtoupper($verb)) );
        return $this->$verb;
    }

    /**
     * @brief a way to help modify a camelCase or PascalCase transform to snake_case
     * @method toSnakeCase
     * @public
     * @param string    $input
     * @return string   $output
     */
    protected static function toSnakeCase($input) {
        $matches = [];
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        return implode('_', $matches[0]);
    }
    
    
    /**
     * @brief a static but magic call method to retrieve data from the REQUEST body.
     * @method __callStatic
     * @public
     * @param string    $name
     * @param array     $arguments
     * @return array|string
     */
    public static function __callStatic($name, $arguments) {
        
        if( self::$singleton === null )
            self::getInstance();
        
        
        if(method_exists(self::$singleton, '_' . $name)){
            return call_user_func_array([ self::$singleton, '_' . $name ], $arguments);
        }
        
        if (substr($name, 0, 3) == 'get') {
            $field = substr($name, 3);
            $snake_case = \Trail\Request::toSnakeCase ( $field );
            return self::$singleton->$snake_case;
        }
    }
    
    /**
     * @brief a dynamic but magic call method to retrieve data from the REQUEST body.
     * @method __call
     * @public
     * @param string $name
     * @param array $arguments
     * @return mixed | string 
     */
    public function __call( $name, $arguments ){
        if(method_exists($this, '_' . $name)){
            return call_user_func_array([$this, '_' . $name ], $arguments);
        }
        
        if (substr($name, 0, 3) == 'get') {
            $field = substr($name, 3);
            $snake_case = \Trail\Request::toSnakeCase ( $field );
            return $this->$snake_case;
        }
    }
    
    /**
     * @brief a magic __get method to retrieve whatever was in the original REQUEST
     * body.
     * @method __get
     * @public
     * @param string $name
     * @return string
     */
    public function __get($name) {
        return $this->_get( $name );
    }
    
    /**
     * @var \Trail\Request | null 
     */
    private static $singleton = null;

    /**
     * @brief returns the Request instance
     * @method instance
     * @public
     * @return \Trail\Request
     */
    public static function getInstance() {
        if (! self::$singleton instanceof self) {
            self::$singleton = new self();
        }
        return self::$singleton;
    }

}
