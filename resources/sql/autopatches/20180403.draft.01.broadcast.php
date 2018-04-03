<?php

$table = new DifferentialRevision();
$conn = $table->establishConnection('w');

$drafts = $table->loadAllWhere(
  'status = %s',
  DifferentialRevisionStatus::DRAFT);
foreach ($drafts as $draft) {
  $properties = $draft->getProperties();

  $properties[DifferentialRevision::PROPERTY_SHOULD_BROADCAST] = false;

  queryfx(
    $conn,
    'UPDATE %T SET properties = %s WHERE id = %d',
    id(new DifferentialRevision())->getTableName(),
    phutil_json_encode($properties),
    $draft->getID());
}
