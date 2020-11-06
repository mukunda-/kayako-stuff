<?php
// Load settings.
require "private/settings.php";

session_start();
$_SESSION['logged_in'] = $_SESSION['logged_in'] ?? false;

//--------------------------------------------------------
function Login() {
    global $settings, $logged_in;

    if( isset($_GET['logout']) ) {
        $_SESSION['logged_in'] = false;
        unset($_SESSION['code']);
        unset($_SESSION['oauth_state']);
        unset($_SESSION['auth']);
        unset($_SESSION['access_token']);
    }
    
    if( !$_SESSION["logged_in"] ) {
        if( isset($_GET["code"]) ) {
            if( $_SESSION["oauth_state"] == $_GET["state"] ) {
                $_SESSION["oauth_state"] = null;
                $_SESSION["logged_in"]   = true;
                $_SESSION["auth"] = $_GET["code"];
                Login();
                return;
            }
        }

        $state = uniqid();
        $_SESSION['oauth_state'] = $state;
      
        echo "
        <form action='https://$settings[kayako]/oauth/token/authorize'>
            <input type='hidden' name='response_type' value='code'>
            <input type='hidden' name='response_type' value='code'>
            <input type='hidden' name='client_id' value='$settings[client_id]'>
            <input type='hidden' name='redirect_uri' value='$settings[url]'>
            <input type='hidden' name='scope' value='users conversations'>
            <input type='hidden' name='state' value='$state'>
            <input type='submit' value='log in'>
        </form>";
        $logged_in = false;
    } else {
        echo "
        <form>
            <input type='hidden' name='logout'>
            <input type='submit' value='log out'>
        </form>";
        $logged_in = true;
    }
}
/*
function Request( $method, $endpoint, $query = [], $body = [] ) {

    $method = strtoupper( $method );
    $curl = curl_init( "https://$settings[kayako]$endpoint" );

    if( $method == "GET" ) {
        // Default.
    } else if( $method == "POST" ) {
        curl_setopt( $curl, CURLOPT_POST, true );
        if( !empty($body) )
            curl_setopt( $curl, CURLOPT_POSTFIELDS, $body );
    } else if( $method == "PUSH" || $method == "DELETE" {
        curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $method );
        
    } else {
        throw new InvalidArgumentException( "Invalid method." );
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, headers );
}
*/

function DoTest() {
    global $settings;
    
    if( !isset($_SESSION['access_token']) ) {
        // Fetch access token.
        $post = [
            'code' => $_SESSION['auth'],
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'redirect_uri' => $settings['url'],
            'grant_type' => 'authorization_code'
        ];

        $curl = curl_init( "https://$settings[kayako]/oauth/token" );
        curl_setopt( $curl, CURLOPT_POST, true );
        curl_setopt( $curl, CURLOPT_POSTFIELDS, $post );
        curl_setopt( $curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );

        $json = json_decode( curl_exec($curl) );

        echo "Authentication<br>\n";
        var_dump( $json );
        echo "<br>";

        if( curl_getinfo( $curl, CURLINFO_HTTP_CODE ) != 200 ) {
            echo "Didn't get status 200.<br>\n";
            var_dump( $json );
            return;
        }
        curl_close( $curl );
        // Save this, because we can't get it again without reauthorization.
        // Use the refresh token to get another one.
        $_SESSION['access_token'] = $json->access_token;
    }
    $curl = curl_init( "https://$settings[kayako]/api/v1/cases/1" );
    $access_token = $_SESSION['access_token'];
    echo "Performing request with access token $access_token<br>";
    curl_setopt( $curl, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token"
    ]);

    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    $json = json_decode( curl_exec($curl) );
    curl_close( $curl );
    var_dump( $json );
}

function Run() {
    global $logged_in;
    Login();

    if( !$logged_in ) return;
    echo "
        <form>
            <input type='hidden' name='action' value='test'>
            <input type='submit' value='test action'>
        </form>";
    
    $action = $_GET["action"] ?? null;
    if( $action == "test" ) {
        DoTest();
    }
}

?><html lang="en">
<head>
    <meta charset="utf-8">
    <title>Kayako OAuth test</title>
</head>
<body>
    <?php Run(); ?>
    
</body>
</html>