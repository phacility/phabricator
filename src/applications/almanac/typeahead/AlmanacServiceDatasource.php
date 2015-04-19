<?php

final class AlmanacServiceDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Services');
  }

  public function getPlaceholderText() {
    return pht('Type a service name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $services = id(new AlmanacServiceQuery())
      ->withNamePrefix($raw_query)
      ->setOrder('name');

    $services = $this->executeQuery($services);

    if ($services) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($services, 'getPHID'))
        ->execute();
    } else {
      $handles = array();
    }

    $results = array();
    foreach ($handles as $handle) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($handle->getName())
        ->setPHID($handle->getPHID());
    }

    return $results;
  }

}
