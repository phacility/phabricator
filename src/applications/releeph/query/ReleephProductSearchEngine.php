<?php

final class ReleephProductSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('active', $request->getStr('active'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new ReleephProductQuery())
      ->setOrder(ReleephProductQuery::ORDER_NAME)
      ->needArcanistProjects(true);

    $active = $saved->getParameter('active');
    $value = idx($this->getActiveValues(), $active);
    if ($value !== null) {
      $query->withActive($value);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $form->appendChild(
      id(new AphrontFormSelectControl())
        ->setName('active')
        ->setLabel(pht('Show Products'))
        ->setValue($saved_query->getParameter('active'))
        ->setOptions($this->getActiveOptions()));

  }

  protected function getURI($path) {
    return '/releeph/project/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active'),
      'all' => pht('All'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query
          ->setParameter('active', 'active');
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getActiveOptions() {
    return array(
      'all'       => pht('Active and Inactive Products'),
      'active'    => pht('Active Prodcuts'),
      'inactive'  => pht('Inactive Products'),
    );
  }

  private function getActiveValues() {
    return array(
      'all' => null,
      'active' => 1,
      'inactive' => 0,
    );
  }

}
