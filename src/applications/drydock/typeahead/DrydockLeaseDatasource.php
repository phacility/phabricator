<?php

final class DrydockLeaseDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a lease ID (exact match)...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDrydockApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $leases = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withDatasourceQuery($raw_query)
      ->execute();

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(mpull($leases, 'getPHID'))
      ->execute();

    $results = array();
    foreach ($handles as $handle) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($handle->getName())
        ->setPHID($handle->getPHID());
    }
    return $results;
  }
}
