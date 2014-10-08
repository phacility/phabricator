<?php

final class DiffusionRepositoryDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a repository name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->execute();
    foreach ($repos as $repo) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($repo->getMonogram().' '.$repo->getName())
        ->setURI('/diffusion/'.$repo->getCallsign().'/')
        ->setPHID($repo->getPHID())
        ->setPriorityString($repo->getMonogram());
    }

    return $results;
  }

}
