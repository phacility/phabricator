<?php

final class PhabricatorProjectDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a project name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorApplicationProject';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $projs = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->needImages(true)
      ->execute();
    foreach ($projs as $proj) {
      $closed = null;
      if ($proj->isArchived()) {
        $closed = pht('Archived');
      }

      $proj_result = id(new PhabricatorTypeaheadResult())
        ->setName($proj->getName())
        ->setDisplayType('Project')
        ->setURI('/tag/'.$proj->getPrimarySlug().'/')
        ->setPHID($proj->getPHID())
        ->setIcon($proj->getIcon())
        ->setPriorityType('proj')
        ->setClosed($closed);

      $proj_result->setImageURI($proj->getProfileImageURI());

      $results[] = $proj_result;
    }

    return $results;
  }

}
