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
    foreach ($blueprints as $blueprint) {
      $handle = $handles[$blueprint->getPHID()];

      $result = id(new PhabricatorTypeaheadResult())
        ->setName($handle->getFullName())
        ->setPHID($handle->getPHID());

      if ($blueprint->getIsDisabled()) {
        $result->setClosed(pht('Disabled'));
      }

      $results[] = $result;
    }

    return $results;
  }
}
