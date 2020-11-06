<?php

require 'private/database.php';

//----------------------------------------------------------------------------------------
// Looks up a user from their credentials and returns their properties.
// Visit https://yourdomain.kayako.com/api/v1/me to see what properties there are.
// Returns FALSE on server error. If the user doesn't exist, it will return the error
//  response.
//
function check_user( string $username, string $password ) {
	global $database;
    // Read from the api/v1/me endpoint which returns details about the logged in user.
    // This user will be whatever you specify in the Basic Auth, or the endpoint will
    //  return an error if it fails.
    $curl = curl_init( "https://$database[kayako]/api/v1/me?include=*" );
	curl_setopt( $curl, CURLOPT_USERPWD, "$username:$password");
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    
    $result = curl_exec($curl);
	
    if( !$result ) {
        // Couldn't connect to server.
        return FALSE;
    }
    
    $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
    if( $code >= 500 && $code < 600) {
        // Internal server error.
        return FALSE;
    }
	
    $json = json_decode( $result, true );
    
    // Note that there can still be an error, but it should be a well defined error with
    //  a response from the Kayako server. For example, if the login is incorrect:
    /*
        {
        "status": 401,
        "errors": [
            {
                "code": "AUTHENTICATION_FAILED",
                "message": "Used authentication credentials are invalid or signature verification failed",
                "more_info": "https://developer.kayako.com/api/v1/reference/errors/AUTHENTICATION_FAILED"
            }
        ],
        "notifications": [
            {
                "type": "ERROR",
                "message": "Invalid Email or Password",
                "sticky": false
            }
        ]
    }
    */

    return $json;
}

// JWT uses base64url encoding, a variant of base64 that doesn't use the same special
//  characters.
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Header, Body, and Signature are concatenated together later.
$header = base64url_encode('{ "typ": "JWT", "alg": "HS256" }');

// Sanitize inputs.
$username = $_POST['username'] ?? "";
$password = $_POST['password'] ?? "";
$returnto = $_POST['returnto'] ?? "";
$login    = $database["login"];

$user = check_user( $username, $password );

if( !$user || ($user["errors"] ?? FALSE) ) {
    // If the user isn't found, redirect to the login page.
    // Note that we are not checking the error. A proper implementation will detect
    //  server-side errors and not tell the user that their credentials are incorrect.
	
	// Re-encode returnto to be in an URL query argument.
    $rt = urlencode( $returnto );
	
    // Format error message.
    if( isset($user["errors"]) ) {
        if( isset($user["notifications"]) ) {
            // There is a notification. These are meant to be viewed by the end-user.
            $errmsg = $user["notifications"][0]["message"];
        } else {
            // Display the internal error message.
            $errmsg = $user["errors"][0]["message"];
        }
    } else {
        // We didn't get any response from the server, so make a generic message.
        $errmsg = "Internal server error. Please try again later.";
    }
    $errmsg = urlencode( $errmsg );
	
    header( "Location: $login?type=error&message=$errmsg&returnto=$rt&test=true" );
    return;
}

// Create an SSO token to log in with.
$payload = base64url_encode(
json_encode([
    // There is a quirk here we need to accommodate for. You may need to adjust the time
    //  to something that works. -30 seconds seems to be a safe bet, but this is a bug
    //  that needs to be fixed. Using too close to the present or in the future may fail.
    //  Using too far in the past will fail too.
    'iat'   => time()-30,

    // Just a unique ID to associate with this token.
    'jti'   => uniqid( "", true ),

    // User to log in with.
    'name'  => $user["data"]["full_name"],
    'email' => $user["data"]["emails"][0]["email"],
	
	// I'm not sure why this needs to be specified, but you will get an 
	//  "Identity misrepresentation" error otherwise.
	'role' => $user['data']['role']['type']
]));

//die( $payload );

// Hash everything so far for the verification signature.
$signature = base64url_encode(
    hash_hmac('sha256', "$header.$payload", $database['shared_secret'], true) );

// Combine everything together to form the JWT token.
$jwt = "$header.$payload.$signature";

// If we visit the SSO login portal directly, then we won't have a returnto parameter.
// We can initialize it according to the user role. Note that typically you want to have
//  different login portals for agents and customers. One login portal is for accessing
//  the agent panel, and the other is for accessing the Help Center.
//
if( $returnto == "" ) {
	if( $user["data"]["role"]["type"] == "CUSTOMER" ) {
		// Direct to Help Center
		$returnto = "https://$database[kayako]/Base/SSO/JWT/?type=customer&action=/HelpCenter/Login/Index";
	} else {
		// Direct to Agent Area
		$returnto = "https://$database[kayako]/Base/SSO/JWT/?type=agent&action=/agent";
	}
}
// Forward it to Kayako. returnto should contain the link they want to visit.
header( "Location: $returnto&jwt=$jwt" );
