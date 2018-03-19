<?php
session_start();

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/db.php');


use Phroute\Phroute\RouteCollector;
use Phroute\Phroute\Dispatcher;
$router = new RouteCollector();

$router->any('/', function(){
  return 'Home Page';
});

$router->get('/example', function(){
  return 'This route responds to requests with the GET method at the path /example';
});

$router->get('/login', function(){

  // Generate a random hash and store in the session for security
  $_SESSION['state'] = hash('sha256', microtime(TRUE).rand().$_SERVER['REMOTE_ADDR']);
  unset($_SESSION['access_token']);

  $params = array(
    'client_id' => OAUTH2_CLIENT_ID,
    'redirect_uri' => 'http://localhost:5000/auth',
    'scope' => 'user',
    'state' => $_SESSION['state']
  );

  // Redirect the user to Github's authorization page
  header('Location: ' . GH_URL_AUTHORIZE . '?' . http_build_query($params));
  die();
});

$router->get('/logout', function(){
  unset($_SESSION['access_token']);
  unset($_SESSION['state']);
});

$router->get('/auth', function(){
  // When Github redirects the user back here, there will be a "code" and "state" parameter in the query string
  if(get('code')) {
    // Verify the state matches our stored state
    if(!get('state') || $_SESSION['state'] != get('state')) {
      header('Location: /auth');
      die();
    }

    // Exchange the auth code for a token
    $token = apiRequest(GH_URL_ACCESS_TOKEN, array(
      'client_id' => OAUTH2_CLIENT_ID,
      'client_secret' => OAUTH2_CLIENT_SECRET,
      'redirect_uri' => 'http://localhost:5000/auth',
      'state' => $_SESSION['state'],
      'code' => get('code')
    ));
    $_SESSION['access_token'] = $token->access_token;

    header('Location: /auth');
  }

  if(session('access_token')) {
    $user = apiRequest(GH_URL_API . 'user');

    echo '<h3>Logged In</h3>';
    echo '<h4>' . $user->name . '</h4>';
    echo '<pre>';
    print_r($user);
    echo '</pre>';
    $db->query('REPLACE INTO user ', [
        'id' => $user->id,
        'login' => $user->login,
        'fullname' => $user->name,
        'lastlogin' => date('Y-m-d H:i:s'),
    ]);
  } else {
    echo '<h3>Not logged in</h3>';
    echo '<p><a href="?action=login">Log In</a></p>';
  }
});
$dispatcher =  new Dispatcher($router->getData());
$response = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
echo $response;





function apiRequest($url, $post=FALSE, $headers=array()) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

  if($post)
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

  $headers[] = 'Accept: application/json';

  if(session('access_token'))
    $headers[] = 'Authorization: Bearer ' . session('access_token');

  $headers[] = 'User-Agent: Awesome-Octocat-App'; // https://developer.github.com/v3/#user-agent-required

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);
  return json_decode($response);
}

function get($key, $default=NULL) {
  return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
}

function session($key, $default=NULL) {
  return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
}
