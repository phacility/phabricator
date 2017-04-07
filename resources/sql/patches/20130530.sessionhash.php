<?php

$table = new PhabricatorUser();
$table->openTransaction();
$conn = $table->establishConnection('w');

$sessions = queryfx_all(
  $conn,
  'SELECT userPHID, type, sessionKey FROM %T FOR UPDATE',
  PhabricatorUser::SESSION_TABLE);

foreach ($sessions as $session) {
  queryfx(
    $conn,
    'UPDATE %T SET sessionKey = %s WHERE userPHID = %s AND type = %s',
    PhabricatorUser::SESSION_TABLE,
    PhabricatorHash::weakDigest($session['sessionKey']),
    $session['userPHID'],
    $session['type']);
}

$table->saveTransaction();
