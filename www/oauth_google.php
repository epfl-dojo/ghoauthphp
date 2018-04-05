<?php

require_once(__DIR__ . '/config.php');

session_start();

// Start the login process by sending the user to Github's authorization page
if(get('action') == 'login') {
  // Generate a random hash and store in the session for security
  $_SESSION['state'] = hash('sha256', microtime(TRUE).rand().$_SERVER['REMOTE_ADDR']);
  unset($_SESSION['access_token']);

  $params = array(
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => 'http://localhost:5000',
    'scope' => 'user',
    'state' => $_SESSION['state']
  );

  // Redirect the user to Github's authorization page
  #header('Location: ' . GH_URL_AUTHORIZE . '?' . http_build_query($params));
  header('Location: https://accounts.google.com/o/oauth2/v2/auth?scope=https://www.googleapis.com/auth/plus.login&access_type=offline&include_granted_scopes=true&state=state_parameter_passthrough_value&redirect_uri=http://localhost:5000/oauth_google.php&response_type=code&client_id=' . $params["client_id"]);
  die();
}

// When Github redirects the user back here, there will be a "code" and "state" parameter in the query string
if(get('code')) {
  // Verify the state matches our stored state
// var_dump($_REQUEST);
  // die();


  //if(!get('state') || $_SESSION['state'] != get('state')) {
  //  header('Location: ' . $_SERVER['PHP_SELF']);
  //  die();
  //}

  // Exchange the auth code for a token
  $token = apiRequest(GOOGLE_URL_ACCESS_TOKEN, array(
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => 'http://localhost:5000/oauth_google.php',
    'state'         => $_SESSION['state'],
    'code'          => get('code')
  ));
  var_dump($token);
  die();
  $_SESSION['access_token'] = $token->access_token;

  header('Location: ' . $_SERVER['PHP_SELF']);
}

if(session('access_token')) {
  $user = apiRequest(GH_URL_API . 'user');

  echo '<h3>Logged In</h3>';
  echo '<h4>' . $user->name . '</h4>';
  echo '<pre>';
  print_r($user);
  echo '</pre>';

} else {
  echo '<h3>Not logged in</h3>';
  echo '<p><a href="?action=login">Log In</a></p>';
}


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
