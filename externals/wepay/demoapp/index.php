<?php
require './_shared.php';
?>

<h1>WePay Demo App</h1>
<?php if (empty($_SESSION['wepay_access_token'])): ?>

<a href="login.php">Log in with WePay</a>

<?php else: ?>

<a href="user.php">User info</a>
<br />
<a href="openaccount.php">Open new account</a>
<br />
<a href="accountlist.php">Account list</a>
<br />
<a href="logout.php">Log out</a>

<?php endif; ?>
