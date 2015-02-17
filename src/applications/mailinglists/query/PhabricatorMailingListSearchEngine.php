<?php

final class PhabricatorMailingListSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Mailing Lists');
  }

  public function getApplicationClassName() {
    return 'PhabricatorMailingListsApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorMailingListQuery());

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    // This just makes it clear to the user that the lack of filters is
    // intentional, not a bug.
    $form->appendChild(
      id(new AphrontFormMarkupControl())
        ->setValue(pht('No query filters are available for mailing lists.')));
  }

  protected function getURI($path) {
    return '/mailinglists/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Lists'),
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
    array $lists,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($lists, 'PhabricatorMetaMTAMailingList');

    $view = id(new PHUIObjectItemListView());

    foreach ($lists as $list) {
      $item = new PHUIObjectItemView();

      $item->setHeader($list->getName());
      $item->setHref($list->getURI());
      $item->addAttribute($list->getEmail());
      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-pencil')
          ->setHref($this->getApplicationURI('/edit/'.$list->getID().'/')));

      $view->addItem($item);
    }

    return $view;
  }

}
