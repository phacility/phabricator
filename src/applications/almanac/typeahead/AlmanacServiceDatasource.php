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

    // TODO: When service classes are restricted, it might be nice to customize
    // the title and placeholder text to show which service types can be
    // selected, or show all services but mark the invalid ones disabled and
    // prevent their selection.

    $service_classes = $this->getParameter('serviceClasses');
    if ($service_classes) {
      $services->withServiceClasses($service_classes);
    }

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
