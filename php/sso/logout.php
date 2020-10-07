<?php
require 'private/database.php';
session_start();

$logging_out = false;
if( isset( $_SESSION['kyko_login_cache'] )) {
    unset($_SESSION['kyko_login_cache']);
    $logging_out = true;
}

$type = $_GET['type'] ?? null;
$error = $_GET['message'] ?? null;
$typequery = "";
if( $type ) {
    $type = urlencode($type);
    $error = urlencode($error);
    $typequery = "&type=$type&message=$error";
}

// Can either go directly to here, or go to the Kayako page you want to visit. Going to
//  the Kayako page to log in again will hit you with a few seconds of load time before
//  you're redirected.
$returnto = urlencode("https://$database[kayako]/Base/SSO/JWT/?type=agent&action=/agent/");
header( "Location: $database[login]?returnto=$returnto$typequery" );
return;
?>

(Not actually used down here...)
<!DOCTYPE html>
<html lang='en'>
<head>
<style>
body {background-color:yellow; font-size: 200%}
</style>
</head>
<body>
    <?php
        if( $logging_out ) {
            echo "You've been logged out. we're sad to see you go. 😢😢😢";
        }

        $url = "https://$database[kayako]/agent";
        echo "<a href='$url'>Log in again!</a>";
    ?>
</body>
</html>
