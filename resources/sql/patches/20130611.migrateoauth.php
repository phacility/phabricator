<?php

$table = new PhabricatorUser();
$conn = $table->establishConnection('w');
$table_name = 'user_oauthinfo';

foreach (new LiskRawMigrationIterator($conn, $table_name) as $row) {
  throw new Exception(
    pht(
      'This database has ancient OAuth account data and is too old to '.
      'upgrade directly to a modern software version. Upgrade to a version '.
      'released between June 2013 and February 2019 first, then upgrade to '.
      'a modern version.'));
}
