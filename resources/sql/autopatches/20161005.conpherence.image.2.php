<?php

// Rebuild all Conpherence Room images to profile standards
//
$table = new ConpherenceThread();
$conn = $table->establishConnection('w');
$table_name = 'conpherence_thread';

foreach (new LiskRawMigrationIterator($conn, $table_name) as $row) {

  $images = phutil_json_decode($row['imagePHIDs']);
  if (!$images) {
    continue;
  }

  $file_phid = idx($images, 'original');

  $file = id(new PhabricatorFileQuery())
    ->setViewer(PhabricatorUser::getOmnipotentUser())
    ->withPHIDs(array($file_phid))
    ->executeOne();

  $xform = PhabricatorFileTransform::getTransformByKey(
    PhabricatorFileThumbnailTransform::TRANSFORM_PROFILE);
  $xformed = $xform->executeTransform($file);
  $new_phid = $xformed->getPHID();

  queryfx(
    $conn,
    'UPDATE %T SET profileImagePHID = %s WHERE id = %d',
    $table->getTableName(),
    $new_phid,
    $row['id']);
}
