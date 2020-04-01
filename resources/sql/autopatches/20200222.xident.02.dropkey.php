<?php

// See T13493. This table previously had a UNIQUE KEY on "<accountType,
// accountDomain, accountID>", which is obsolete. The application now violates
// this key, so make sure it gets dropped.

// There's no "IF EXISTS" modifier for "ALTER TABLE" so run this as a PHP patch
// instead of an SQL patch.

$table = new PhabricatorExternalAccount();
$conn = $table->establishConnection('w');

try {
  queryfx(
    $conn,
    'ALTER TABLE %R DROP KEY %T',
    $table,
    'account_details');
} catch (AphrontQueryException $ex) {
  // Ignore.
}
