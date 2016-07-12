<?php

final class DiffusionRefDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Branches');
  }

  public function getPlaceholderText() {
    // TODO: This is really "branch, tag, bookmark or ref" but we are only
    // using it to pick branches for now and sometimes the UI won't let you
    // pick some of these types. See also "Browse Branches" above.
    return pht('Type a branch name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $query = id(new PhabricatorRepositoryRefCursorQuery())
      ->withDatasourceQuery($raw_query);

    $types = $this->getParameter('refTypes');
    if ($types) {
      $query->withRefTypes($types);
    }

    $repository_phids = $this->getParameter('repositoryPHIDs');
    if ($repository_phids) {
      $query->withRepositoryPHIDs($repository_phids);
    }

    $refs = $this->executeQuery($query);

    $results = array();
    foreach ($refs as $ref) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($ref->getRefName())
        ->setPHID($ref->getPHID());
    }

    return $results;
  }

}
