<?php

require_once('../lib/_autoload.php');

// Authenticate the user with SimpleSAML. Three lines of easy code.
$as = new \SimpleSAML\Auth\Simple('default-sp');
$as->requireAuth();
$attributes = $as->getAttributes();

// The Kayako SSO secret is shared through a claim.
$kayakoSecret = $attributes['KayakoSecret'][0];
var_dump($attributes);
// Email, name, and role are the bare minimum that need to be specified to log into
//  Kayako. Role dictates what permissions they have in the Kayako Agent Area, which can
//  be "owner", "admin", "agent", "collaborator", or "customer".
// We should be pulling this from a claim or transforming it from another set of
//                                             permissions for the Azure AD user.
if( isset( $attributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'] )) {
   // Big caveat here. Email might not be set for the user, but this is required for Kayako.
   $email = $attributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'][0];
} else {
   // We're falling back to their username, which should *usually* be an email address format,
   //  but that might not always be the case. This logic should be adjusted according to needs.
   $email = $attributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name'][0];
}
$name  = $attributes['http://schemas.microsoft.com/identity/claims/displayname'][0];
$role  = 'agent'; // TODO, pull this from a claim.

//---------------------------------------------------------------------------------------
// JWT uses base64url encoding, which has a different couple of symbols.
function base64url_encode($data) {
   return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// The header is a basic constant that defines the JWT.
$header = base64url_encode('{ "typ": "JWT", "alg": "HS256" }');

// Payload is custom for Kayako. See https://developer.kayako.com/docs/single_sign_on/implementation/
$payload = base64url_encode(json_encode([
   // There is a known issue with `iat`, where we need to subtract a small offset,
   //                                     otherwise it might be considered expired.
   'iat' => time() - 30,
   
   // This is just any unique ID for this token. PHP generator is fine -- use extra
   //  entropy.
   'jti' => uniqid( "", true ),
   
   // These are the custom Kayako claims.
   'name'  => $name,
   'email' => $email,
   'role'  => $role
]));

// Sign everything using the Kayako SSO secret. This is configured in the Kayako
//          Authentication settings, and then shared through a claim via Azure AD.
$signature = base64url_encode(hash_hmac( 'sha256', "$header.$payload", $kayakoSecret, true ));

// Combine it all for the final token.
$jwt = "$header.$payload.$signature";

// The returnto query param is from Kayako, so if you visit a private URL such as
//  "https://domain.kayako.com/agent/conversations/163" then you will be redirected to
//  there after the login process finishes.
// If not given, then generate a base login URL. We don't know the Kayako URL in that
//                     case, so we fetch it from a claim constant that must be shared.
$returnto = $_REQUEST['returnto'] ?? "";
if( $returnto == "" ) {
   // Use default.
   $kayakoURL = $attributes['KayakoURL'][0];
   $returnto = "$kayakoURL/Base/SSO/JWT/?type=agent&action=/agent";
}

// Somewhere around here, extra work could potentially be done to -update- the user's
//  profile in Kayako according to what claims are current.

header( "Location: $returnto&jwt=$jwt" );
