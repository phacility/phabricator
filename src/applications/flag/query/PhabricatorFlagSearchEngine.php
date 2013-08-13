<?php

final class PhabricatorFlagSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter('colors', $request->getArr('colors'));
    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorFlagQuery())
      ->needHandles(true)
      ->withOwnerPHIDs(array($this->requireViewer()->getPHID()));

    $colors = $saved->getParameter('colors');
    if ($colors) {
      $query->withColors($colors);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $form->appendChild(
      id(new PhabricatorFlagSelectControl())
        ->setName('colors')
        ->setLabel(pht('Colors'))
        ->setValue($saved_query->getParameter('colors', array())));

  }

  protected function getURI($path) {
    return '/flag/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('Flagged'),
    );

    return $names;
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

}
