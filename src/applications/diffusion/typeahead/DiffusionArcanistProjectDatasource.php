<?php

final class DiffusionArcanistProjectDatasource
  extends PhabricatorTypeaheadDatasource {

  public function isBrowsable() {
    // TODO: We should probably make this browsable, or maybe remove it.
    return false;
  }

  public function getBrowseTitle() {
    return pht('Browse Arcanist Projects');
  }

  public function getPlaceholderText() {
    return pht('Type an arcanist project name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $arcprojs = id(new PhabricatorRepositoryArcanistProject())->loadAll();
    foreach ($arcprojs as $proj) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($proj->getName())
        ->setPHID($proj->getPHID());
    }

    return $results;
  }

}
