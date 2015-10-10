<?php

final class DrydockAuthorizationSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $blueprint;

  public function setBlueprint(DrydockBlueprint $blueprint) {
    $this->blueprint = $blueprint;
    return $this;
  }

  public function getBlueprint() {
    return $this->blueprint;
  }

  public function getResultTypeDescription() {
    return pht('Drydock Authorizations');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDrydockApplication';
  }

  public function canUseInPanelContext() {
    return false;
  }

  public function newQuery() {
    $query = new DrydockAuthorizationQuery();

    $blueprint = $this->getBlueprint();
    $query->withBlueprintPHIDs(array($blueprint->getPHID()));

    return $query;
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function getURI($path) {
    $blueprint = $this->getBlueprint();
    $id = $blueprint->getID();
    return "/drydock/blueprint/{$id}/authorizations/".$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Authorizations'),
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
    array $authorizations,
    PhabricatorSavedQuery $query,
    array $handles) {

    $list = id(new DrydockAuthorizationListView())
      ->setUser($this->requireViewer())
      ->setAuthorizations($authorizations);

    $result = new PhabricatorApplicationSearchResultView();
    $result->setTable($list);

    return $result;
  }

}
