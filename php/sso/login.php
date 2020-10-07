<?php
session_start();

if( isset($_SESSION['kyko_login_cache']) ) {
    // We're logged in.
    //header( TODO
}

?>
<!DOCTYPE html>
<html lang='en'>
<head>
<style>
body {background-color:yellow; font-size: 200%}
</style>
</head>
<body>
    Fancy SSO login page 😎
    <form action="dologin.php" method="POST">
        <label for="username">Username</label>
        <input name="username" placeholder="email@domain" value="">
        <label for="password">Password</label>
        <input name="password" type="password" value="">
        <input type="hidden" name="returnto" value="<?=htmlspecialchars($_GET['returnto'])?>">
        <input type="submit" value="LOG IN!">
    </form>

    <?php
        $type = $_GET["type"] ?? null;
        if( $type == "error" ) {
            echo "<p>ERROR: $_GET[message]</p>";
        }
    ?>
</body>
</html>