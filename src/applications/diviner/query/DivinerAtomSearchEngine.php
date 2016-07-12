<?php

final class DivinerAtomSearchEngine extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Documentation Atoms');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDivinerApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'bookPHIDs',
      $this->readPHIDsFromRequest($request, 'bookPHIDs'));
    $saved->setParameter(
      'repositoryPHIDs',
      $this->readPHIDsFromRequest($request, 'repositoryPHIDs'));
    $saved->setParameter('name', $request->getStr('name'));
    $saved->setParameter(
      'types',
      $this->readListFromRequest($request, 'types'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new DivinerAtomQuery());

    $books = $saved->getParameter('bookPHIDs');
    if ($books) {
      $query->withBookPHIDs($books);
    }

    $repository_phids = $saved->getParameter('repositoryPHIDs');
    if ($repository_phids) {
      $query->withRepositoryPHIDs($repository_phids);
    }

    $name = $saved->getParameter('name');
    if ($name) {
      $query->withNameContains($name);
    }

    $types = $saved->getParameter('types');
    if ($types) {
      $query->withTypes($types);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setLabel(pht('Name Contains'))
        ->setName('name')
        ->setValue($saved->getParameter('name')));

    $all_types = array();
    foreach (DivinerAtom::getAllTypes() as $type) {
      $all_types[$type] = DivinerAtom::getAtomTypeNameString($type);
    }
    asort($all_types);

    $types = $saved->getParameter('types', array());
    $types = array_fuse($types);
    $type_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Types'));
    foreach ($all_types as $type => $name) {
      $type_control->addCheckbox(
        'types[]',
        $type,
        $name,
        isset($types[$type]));
    }
    $form->appendChild($type_control);

    $form->appendControl(
      id(new AphrontFormTokenizerControl())
        ->setDatasource(new DivinerBookDatasource())
        ->setName('bookPHIDs')
        ->setLabel(pht('Books'))
        ->setValue($saved->getParameter('bookPHIDs')));

    $form->appendControl(
       id(new AphrontFormTokenizerControl())
         ->setLabel(pht('Repositories'))
         ->setName('repositoryPHIDs')
         ->setDatasource(new DiffusionRepositoryDatasource())
         ->setValue($saved->getParameter('repositoryPHIDs')));
  }

  protected function getURI($path) {
    return '/diviner/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Atoms'),
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
    array $symbols,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($symbols, 'DivinerLiveSymbol');

    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($symbols as $symbol) {
      $type = $symbol->getType();
      $type_name = DivinerAtom::getAtomTypeNameString($type);

      $item = id(new PHUIObjectItemView())
        ->setHeader($symbol->getTitle())
        ->setHref($symbol->getURI())
        ->addAttribute($symbol->getSummary())
        ->addIcon('none', $type_name);

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No books found.'));

    return $result;
  }

}
