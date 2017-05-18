<?php

$project_table = new PhabricatorProject();
$conn_w = $project_table->establishConnection('w');
$conn_w->openTransaction();

$src_table = 'project_legacytransaction';
$dst_table = 'project_transaction';

echo pht('Migrating Project transactions to new format...')."\n";

$content_source = PhabricatorContentSource::newForSource(
  PhabricatorOldWorldContentSource::SOURCECONST)->serialize();

$rows = new LiskRawMigrationIterator($conn_w, $src_table);
foreach ($rows as $row) {
  $id = $row['id'];

  $project_id = $row['projectID'];

  echo pht('Migrating transaction #%d (Project %d)...', $id, $project_id)."\n";

  $project_row = queryfx_one(
    $conn_w,
    'SELECT phid FROM %T WHERE id = %d',
    $project_table->getTableName(),
    $project_id);
  if (!$project_row) {
    continue;
  }

  $project_phid = $project_row['phid'];

  $type_map = array(
    'name' => PhabricatorProjectNameTransaction::TRANSACTIONTYPE,
    'members' => PhabricatorProjectTransaction::TYPE_MEMBERS,
    'status' => PhabricatorProjectStatusTransaction::TRANSACTIONTYPE,
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
    PhabricatorProjectProjectPHIDType::TYPECONST);

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
    $project_phid,

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
