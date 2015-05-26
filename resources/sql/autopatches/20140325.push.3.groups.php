<?php

$conn_w = id(new PhabricatorRepository())->establishConnection('w');

echo pht('Adding transaction log event groups...')."\n";

$logs = queryfx_all(
  $conn_w,
  'SELECT * FROM %T GROUP BY transactionKey ORDER BY id ASC',
  'repository_pushlog');
foreach ($logs as $log) {
  $id = $log['id'];
  echo pht('Migrating log %d...', $id)."\n";
  if ($log['pushEventPHID']) {
    continue;
  }

  $event_phid = id(new PhabricatorRepositoryPushEvent())->generatePHID();

  queryfx(
    $conn_w,
    'INSERT INTO %T (phid, repositoryPHID, epoch, pusherPHID, remoteAddress,
      remoteProtocol, rejectCode, rejectDetails)
     VALUES (%s, %s, %d, %s, %d, %s, %d, %s)',
    'repository_pushevent',
    $event_phid,
    $log['repositoryPHID'],
    $log['epoch'],
    $log['pusherPHID'],
    $log['remoteAddress'],
    $log['remoteProtocol'],
    $log['rejectCode'],
    $log['rejectDetails']);

  queryfx(
    $conn_w,
    'UPDATE %T SET pushEventPHID = %s WHERE transactionKey = %s',
    'repository_pushlog',
    $event_phid,
    $log['transactionKey']);
}

echo pht('Done.')."\n";
