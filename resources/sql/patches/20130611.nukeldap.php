<?php

// NOTE: We aren't using PhabricatorUserLDAPInfo anywhere here because it is
// being nuked by this change

$table = new PhabricatorUser();
$table_name = 'user_ldapinfo';
$conn_w = $table->establishConnection('w');

$xaccount = new PhabricatorExternalAccount();

echo "Migrating LDAP to ExternalAccount...\n";

$rows = queryfx_all($conn_w, 'SELECT * FROM %T', $table_name);
foreach ($rows as $row) {
  echo "Migrating row ID #".$row['id'].".\n";
  $user = id(new PhabricatorUser())->loadOneWhere(
    'id = %d',
    $row['userID']);
  if (!$user) {
    echo "Bad user ID!\n";
    continue;
  }


  $xaccount = id(new PhabricatorExternalAccount())
    ->setUserPHID($user->getPHID())
    ->setAccountType('ldap')
    ->setAccountDomain('self')
    ->setAccountID($row['ldapUsername'])
    ->setUsername($row['ldapUsername'])
    ->setDateCreated($row['dateCreated']);

  try {
    $xaccount->save();
  } catch (Exception $ex) {
    phlog($ex);
  }
}

echo "Done.\n";
