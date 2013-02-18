<?php

echo "Updating channel IDs of previous chatlog events...\n";
$event_table = new PhabricatorChatLogEvent();
$channel_table = new PhabricatorChatLogChannel();

$event_table->openTransaction();
$channel_table->openTransaction();

$event_table->beginReadLocking();
$channel_table->beginReadLocking();

$events = new LiskMigrationIterator($event_table);

foreach ($events as $event) {
  if ($event->getChannelID()) {
    continue;
  }

  $matched = $channel_table->loadOneWhere(
    "channelName = %s AND serviceName = %s AND serviceType = %s",
    $event->getChannel(),
    '',
    '');

  if (!$matched) {
    $matched = id(new PhabricatorChatLogChannel())
      ->setChannelName($event->getChannel())
      ->setServiceType('')
      ->setServiceName('')
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy(PhabricatorPolicies::POLICY_USER)
      ->save();
  }

  queryfx(
    $event->establishConnection('w'),
    'UPDATE %T SET channelID = %d WHERE id = %d',
    $event->getTableName(),
    $matched->getID(),
    $event->getID());
}

$event_table->endReadLocking();
$channel_table->endReadLocking();

$event_table->saveTransaction();
$channel_table->saveTransaction();

echo "\nDone.\n";
