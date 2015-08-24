<?php

final class DrydockBlueprintDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a blueprint name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDrydockApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $blueprints = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withDatasourceQuery($raw_query)
      ->execute();

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(mpull($blueprints, 'getPHID'))
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
