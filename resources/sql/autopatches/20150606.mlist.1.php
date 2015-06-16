<?php

$conn_r = id(new PhabricatorMetaMTAMail())->establishConnection('r');

$rows = queryfx_all(
  $conn_r,
  'SELECT phid, email FROM %T',
  'metamta_mailinglist');
if (!$rows) {
  echo pht('No mailing lists to migrate.')."\n";
  return;
}

$list_map = array();
foreach ($rows as $row) {
  $list_map[phutil_utf8_strtolower($row['email'])] = $row['phid'];
}

$emails = id(new PhabricatorUserEmail())->loadAllWhere(
  'address IN (%Ls)',
  array_keys($list_map));
if (!$emails) {
  echo pht('No mailing lists match addresses.')."\n";
  return;
}

// Create a map from old mailing list PHIDs to new user PHIDs.
$map = array();
foreach ($emails as $email) {
  $user_phid = $email->getUserPHID();
  if (!$user_phid) {
    continue;
  }

  $address = $email->getAddress();
  $address = phutil_utf8_strtolower($address);
  if (isset($list_map[$address])) {
    $map[$list_map[$address]] = $user_phid;
  }
}

if (!$map) {
  echo pht('No mailing lists match users.')."\n";
  return;
}

echo pht('Migrating Herald conditions which use mailing lists..')."\n";

$table = new HeraldCondition();
$conn_w = $table->establishConnection('w');
foreach (new LiskMigrationIterator($table) as $condition) {
  $name = $condition->getFieldName();
  if ($name == 'cc') {
    // Okay, we can migrate these.
  } else {
    // This is not a condition type which has mailing lists in its value, so
    // don't try to migrate it.
    continue;
  }

  $value = $condition->getValue();
  if (!is_array($value)) {
    // Only migrate PHID lists.
    continue;
  }

  foreach ($value as $v) {
    if (!is_string($v)) {
      // Only migrate PHID lists where all members are PHIDs.
      continue 2;
    }
  }

  $new = array();
  $any_change = false;
  foreach ($value as $v) {
    if (isset($map[$v])) {
      $new[] = $map[$v];
      $any_change = true;
    } else {
      $new[] = $v;
    }
  }

  if (!$any_change) {
    continue;
  }

  $id = $condition->getID();

  queryfx(
    $conn_w,
    'UPDATE %T SET value = %s WHERE id = %d',
    $table->getTableName(),
    json_encode($new),
    $id);


  echo pht('Updated mailing lists in Herald condition %d.', $id)."\n";
}

$table = new HeraldAction();
$conn_w = $table->establishConnection('w');
foreach (new LiskMigrationIterator($table) as $action) {
  $name = $action->getAction();
  if ($name == 'addcc' || $name == 'remcc') {
    // Okay, we can migrate these.
  } else {
    // This is not an action type which has mailing lists in its targets, so
    // don't try to migrate it.
    continue;
  }

  $value = $action->getTarget();
  if (!is_array($value)) {
    // Only migrate PHID lists.
    continue;
  }

  foreach ($value as $v) {
    if (!is_string($v)) {
      // Only migrate PHID lists where all members are PHIDs.
      continue 2;
    }
  }

  $new = array();
  $any_change = false;
  foreach ($value as $v) {
    if (isset($map[$v])) {
      $new[] = $map[$v];
      $any_change = true;
    } else {
      $new[] = $v;
    }
  }

  if (!$any_change) {
    continue;
  }

  $id = $action->getID();

  queryfx(
    $conn_w,
    'UPDATE %T SET target = %s WHERE id = %d',
    $table->getTableName(),
    json_encode($new),
    $id);

  echo pht('Updated mailing lists in Herald action %d.', $id)."\n";
}
