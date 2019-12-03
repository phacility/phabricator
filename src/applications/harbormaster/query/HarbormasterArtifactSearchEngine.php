<?php

final class HarbormasterArtifactSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Harbormaster Artifacts');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function newQuery() {
    return new HarbormasterBuildArtifactQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Targets'))
        ->setKey('buildTargetPHIDs')
        ->setAliases(
          array(
            'buildTargetPHID',
            'buildTargets',
            'buildTarget',
            'targetPHIDs',
            'targetPHID',
            'targets',
            'target',
          ))
        ->setDescription(
          pht('Search for artifacts attached to particular build targets.')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['buildTargetPHIDs']) {
      $query->withBuildTargetPHIDs($map['buildTargetPHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/harbormaster/artifact/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Artifacts'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $artifacts,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($artifacts, 'HarbormasterBuildArtifact');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    foreach ($artifacts as $artifact) {
      $id = $artifact->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Artifact %d', $id));

      $list->addItem($item);
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list)
      ->setNoDataString(pht('No artifacts found.'));
  }

}
