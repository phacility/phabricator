<?php

final class PhabricatorFlagSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter('colors', $request->getArr('colors'));
    $saved->setParameter('group', $request->getStr('group'));
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
    $group = $saved->getParameter('group');
    $options = $this->getGroupOptions();
    if ($group && isset($options[$group])) {
      $query->setGroupBy($group);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $form
      ->appendChild(
        id(new PhabricatorFlagSelectControl())
        ->setName('colors')
        ->setLabel(pht('Colors'))
        ->setValue($saved_query->getParameter('colors', array())))
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setName('group')
        ->setLabel(pht('Group By'))
        ->setValue($saved_query->getParameter('group'))
        ->setOptions($this->getGroupOptions()));

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

  private function getGroupOptions() {
    return array(
      PhabricatorFlagQuery::GROUP_NONE => pht('None'),
      PhabricatorFlagQuery::GROUP_COLOR => pht('Color'),
    );
  }

}
