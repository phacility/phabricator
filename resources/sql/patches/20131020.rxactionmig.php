<?php

$role_table = new PhabricatorRole();
$conn_w = $role_table->establishConnection('w');
$conn_w->openTransaction();

$src_table = 'role_legacytransaction';
$dst_table = 'role_transaction';

echo pht('Migrating Role transactions to new format...')."\n";

$content_source = PhabricatorContentSource::newForSource(
  PhabricatorOldWorldContentSource::SOURCECONST)->serialize();

$rows = new LiskRawMigrationIterator($conn_w, $src_table);
foreach ($rows as $row) {
  $id = $row['id'];

  $role_id = $row['roleID'];

  echo pht('Migrating transaction #%d (Role %d)...', $id, $role_id)."\n";

  $role_row = queryfx_one(
    $conn_w,
    'SELECT phid FROM %T WHERE id = %d',
    $role_table->getTableName(),
    $role_id);
  if (!$role_row) {
    continue;
  }

  $role_phid = $role_row['phid'];

  $type_map = array(
    'name' => PhabricatorRoleNameTransaction::TRANSACTIONTYPE,
    'members' => PhabricatorRoleTransaction::TYPE_MEMBERS,
    'status' => PhabricatorRoleStatusTransaction::TRANSACTIONTYPE,
    'canview' => PhabricatorTransactions::TYPE_VIEW_POLICY,
    'canedit' => PhabricatorTransactions::TYPE_EDIT_POLICY,
    'canjoin' => PhabricatorTransactions::TYPE_JOIN_POLICY,
  );

  $new_type = idx($type_map, $row['transactionType']);
  if (!$new_type) {
    continue;
  }

  $xaction_phid = PhabricatorPHID::generateNewPHID(
    PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST,
    PhabricatorRoleRolePHIDType::TYPECONST);

  queryfx(
    $conn_w,
    'INSERT IGNORE INTO %T
      (phid, authorPHID, objectPHID,
        viewPolicy, editPolicy, commentPHID, commentVersion, transactionType,
        oldValue, newValue, contentSource, metadata,
        dateCreated, dateModified)
      VALUES
      (%s, %s, %s,
        %s, %s, %ns, %d, %s,
        %s, %s, %s, %s,
        %d, %d)',
    $dst_table,

    // PHID, Author, Object
    $xaction_phid,
    $row['authorPHID'],
    $role_phid,

    // View, Edit, Comment, Version, Type
    'public',
    $row['authorPHID'],
    null,
    0,
    $new_type,

    // Old, New, Source, Meta,
    $row['oldValue'],
    $row['newValue'],
    $content_source,
    '{}',

    // Created, Modified
    $row['dateCreated'],
    $row['dateModified']);

}

$conn_w->saveTransaction();
echo pht('Done.')."\n";
