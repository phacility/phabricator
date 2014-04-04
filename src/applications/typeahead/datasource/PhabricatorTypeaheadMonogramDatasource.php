<?php

final class PhabricatorTypeaheadMonogramDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type an object name...');
  }

  public function getDatasourceApplicationClass() {
    return null;
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($raw_query))
      ->execute();
    if ($objects) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($objects, 'getPHID'))
        ->execute();
      $handle = head($handles);
      if ($handle) {
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setName($handle->getFullName())
          ->setDisplayType($handle->getTypeName())
          ->setURI($handle->getURI())
          ->setPHID($handle->getPHID())
          ->setPriorityType('jump');
      }
    }

    return $results;
  }

}
