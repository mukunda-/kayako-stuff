<?php

require 'private/database.php';
session_start();

// JWT uses base64url encoding
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$header = base64url_encode('{ "typ": "JWT", "alg": "HS256" }');
$iat = time();

$username = $_POST['username'] ?? "";
$password = $_POST['password'] ?? "";
$returnto = $_POST['returnto'] ?? "";
$login    = $database["login"];

$user = $database["users"][$username] ?? null;
if( !$user || $user["password"] != $password ) {
    header( "Location: $login?type=error"
            ."&message=Invalid%20credentials!!!"
            ."&returnto=" . urlencode($returnto) );
    return;
}

$payload = base64url_encode(json_encode([
    'iat'   => time()-30,
    'jti'   => md5($database['shared_secret'] . ':' . time()),
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role']
]));

$signature = base64url_encode(hash_hmac( 'sha256', "$header.$payload", $database['shared_secret'], true ));
$jwt = "$header.$payload.$signature";

$_SESSION['kayako_login_cache'] = $jwt;
header( "Location: $returnto&jwt=$jwt" );