<?php

final class ReleephBranchSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $projectID;

  public function setProjectID($project_id) {
    $this->projectID = $project_id;
    return $this;
  }

  public function getProjectID() {
    return $this->projectID;
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('active', $request->getStr('active'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new ReleephBranchQuery())
      ->needCutPointCommits(true)
      ->withProjectIDs(array($this->getProjectID()));

    $active = $saved->getParameter('active');
    $value = idx($this->getActiveValues(), $active);
    if ($value !== null) {
      $query->withStatus($value);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $form->appendChild(
      id(new AphrontFormSelectControl())
        ->setName('active')
        ->setLabel(pht('Show Branches'))
        ->setValue($saved_query->getParameter('active'))
        ->setOptions($this->getActiveOptions()));
  }

  protected function getURI($path) {
    return '/releeph/project/'.$this->getProjectID().'/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'open' => pht('Open'),
      'all' => pht('All'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'open':
        return $query
          ->setParameter('active', 'open');
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getActiveOptions() {
    return array(
      'open' => pht('Open Branches'),
      'all' => pht('Open and Closed Branches'),
    );
  }

  private function getActiveValues() {
    return array(
      'open' => ReleephBranchQuery::STATUS_OPEN,
      'all' => ReleephBranchQuery::STATUS_ALL,
    );
  }

}
