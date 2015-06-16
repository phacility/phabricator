<?php

$table = new PhabricatorPaste();
$x_table = new PhabricatorPasteTransaction();

$conn_w = $table->establishConnection('w');
$conn_w->openTransaction();

echo pht('Adding transactions for existing paste objects...')."\n";

$rows = new LiskRawMigrationIterator($conn_w, 'pastebin_paste');
foreach ($rows as $row) {

  $id = $row['id'];
  echo pht('Adding transactions for paste id %d...', $id)."\n";

  $xaction_phid = PhabricatorPHID::generateNewPHID(
    PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST);

  queryfx(
    $conn_w,
    'INSERT INTO %T (phid, authorPHID, objectPHID, viewPolicy, editPolicy,
        transactionType, oldValue, newValue,
        contentSource, metadata, dateCreated, dateModified,
        commentVersion)
      VALUES (%s, %s, %s, %s, %s, %s, %ns, %ns, %s, %s, %d, %d, %d)',
    $x_table->getTableName(),
    $xaction_phid,
    $row['authorPHID'],
    $row['phid'],
    'public',
    $row['authorPHID'],
    PhabricatorPasteTransaction::TYPE_CONTENT,
    'null',
    $row['filePHID'],
    PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_LEGACY,
      array())->serialize(),
    '[]',
    $row['dateCreated'],
    $row['dateCreated'],
    0);

}

$conn_w->saveTransaction();

echo pht('Done.')."\n";
