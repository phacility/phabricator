<?php

final class PhabricatorWorkerBulkJobPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'BULK';

  public function getTypeName() {
    return pht('Bulk Job');
  }

  public function newObject() {
    return new PhabricatorWorkerBulkJob();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorWorkerBulkJobQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $job = $objects[$phid];

      $id = $job->getID();

      $handle->setName(pht('Bulk Job %d', $id));
    }
  }

}
