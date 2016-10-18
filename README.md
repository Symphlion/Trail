
### To use it

first, time to require the packages ofcourse..
        
```
    composer require symphlion/trail
```
   
Somewhere in your code, make sure to autoload it all
        
```
require '../vendor/autoload.php';

```

And after that, you can start using it.

```

use \Trail\Router;

Router::get('/account/:id', function( $id ) {
    
    echo 'The ID is ' . $id;
    
});


// After defining your routes, we need to call 1 method to bootrstrap the checking
Router::verify();

// to retrieve the instance, simply call it
$router = Router::instance();

$router->get(
    '/events/birthday/:selection', 
    '{namespace}@allBirthdays', 
    [':selection' => '[a-zA-Z\-]{1,3}']
);

// you can specify both a name and a group for a route

$router->get(
    '/users/:id', 
    function( $selection ) {
        echo 'This is your selection!';
    },
    ['name' => 'user-overview'] // this gives this route the name: user-overview
);

// A different way to name a route:
$router->get(
    '/users/:id', 
    function( $selection ) {
        echo 'This is your selection!';
    }
)->name('user-overview');

// we also support collections
// In fact, we encourage collections, as it narrows down the search paths to follow
// hence making it faster to react and match a route

$collections = [
    [
        'name' => 'account', 
        'namespace' => '\\App\\Account', 
        'prefix' => true, 
        'scheme' => 'http', 
        'domain' => 'www.your-domain.com'
    ]
];

// optionally, you can also specify the entire routing array within a collection 
// like so 

$collections = [
    [
        'name' => 'account',                // required, every collection needs a name right? however, you can also specify the key of this array as the name
        'path' => '/accounts',              // required, the scoped path to listen for
        'namespace' => '\\App\\Account',    // optional but highly recommended
        'prefix' => true,                   // optional, whether you want methods to be prefixed by the request_method 
        'scheme' => 'http',                 // optional, liten to a specific host scheme
        'domain' => 'www.your-domain.com',  // optional, listen to a specific domain
        'routes' => [
            [
                ['get', 'post', 'put'],
                '/:id'                      // notice how a route within a collection will scope to the /accounts path from it`s parent collection
            ]
        ]
    ]
];