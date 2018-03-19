<?php

$dsn = 'sqlite:'. __DIR__ .'/db/ghoauthphpdb.db';
$db = new Nette\Database\Connection($dsn);
// $database->query('INSERT INTO user ', [
//     'id' => 121212,
//     'login' => 'me',
//     'fullname' => 'my fullname',
// ]);
//$db->query('SELECT * FROM user')->dump();
