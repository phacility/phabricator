<?php

echo pht('Updating channel IDs of previous chatlog events...')."\n";
$event_table = new PhabricatorChatLogEvent();
$channel_table = new PhabricatorChatLogChannel();

$event_table->openTransaction();
$channel_table->openTransaction();

$event_table->beginReadLocking();
$channel_table->beginReadLocking();

$events = new LiskMigrationIterator($event_table);
$conn_w = $channel_table->establishConnection('w');

foreach ($events as $event) {
  if ($event->getChannelID()) {
    continue;
  }

  $event_row = queryfx_one(
    $conn_w,
    'SELECT channel FROM %T WHERE id = %d',
    $event->getTableName(),
    $event->getID());
  $event_channel = $event_row['channel'];

  $matched = queryfx_one(
    $conn_w,
    'SELECT * FROM %T WHERE
      channelName = %s AND serviceName = %s AND serviceType = %s',
    $channel_table->getTableName(),
    $event_channel,
    '',
    '');

  if (!$matched) {
    $matched = id(new PhabricatorChatLogChannel())
      ->setChannelName($event_channel)
      ->setServiceType('')
      ->setServiceName('')
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy(PhabricatorPolicies::POLICY_USER)
      ->save();
    $matched_id = $matched->getID();
  } else {
    $matched_id = $matched['id'];
  }

  queryfx(
    $event->establishConnection('w'),
    'UPDATE %T SET channelID = %d WHERE id = %d',
    $event->getTableName(),
    $matched_id,
    $event->getID());
}

$event_table->endReadLocking();
$channel_table->endReadLocking();

$event_table->saveTransaction();
$channel_table->saveTransaction();

echo "\n".pht('Done.')."\n";
