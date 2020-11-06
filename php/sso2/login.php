<!DOCTYPE html>
<html lang='en'>
<head>
<style>
body {
	/* Mustard */
    background-color: #F2D65E; 
    font-size: 200%;
}
</style>
</head>
<body>
    <p>SSO Login Test</p>
    <form action="dologin.php" method="POST">
	
        <label for="username">Username</label>
        <input name="username" placeholder="email@domain" value="">
		
        <label for="password">Password</label>
        <input name="password" type="password" value="">
		
		<!-- This needs to be forwarded to the SSO login endpoint. It's given by Kayako. -->
        <input type="hidden" name="returnto" value="<?=htmlspecialchars($_GET['returnto']??"")?>">
        <input type="submit" value="Log in">
    </form>

    <?php
        $type = $_GET["type"] ?? null;
        if( $type == "error" ) {
            echo "<p>ERROR: $_GET[message]</p>";
        }
    ?>
</body>
</html>