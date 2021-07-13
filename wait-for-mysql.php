#!/usr/bin/env php
<?php
// Get unbuffered STDOUT stream
$stdout = fopen('php://stdout', 'w');

$host = getenv('MYSQL_HOST');
$port = getenv('MYSQL_PORT');
$user = getenv('MYSQL_USER');
$pass = getenv('MYSQL_PASS');

$max_tries = 10;
$tries = 0;

fwrite($stdout, "Testing connection to MySQL host '" . $host . ":" . $port . "' as user '" . $user . "'");

while ($tries < $max_tries) {
    $mysql = mysqli_connect($host, $user, $pass, '', $port);

    if (!mysqli_connect_errno()) {
        fwrite($stdout, "\nConnection ready!\n");
        exit(0);
    }

    fwrite($stdout, "Connection not ready. Retrying...");
    $tries++;
    sleep(2);
}

fwrite($stdout, "\nMax tries reached. Connection failed!\n");
exit(1);
?>
