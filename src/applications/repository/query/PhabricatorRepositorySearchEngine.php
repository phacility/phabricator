<?php

final class PhabricatorRepositorySearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('callsigns', $request->getStrList('callsigns'));
    $saved->setParameter('status', $request->getStr('status'));
    $saved->setParameter('order', $request->getStr('order'));
    $saved->setParameter('types', $request->getArr('types'));
    $saved->setParameter('name', $request->getStr('name'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorRepositoryQuery())
      ->needCommitCounts(true)
      ->needMostRecentCommits(true);

    $callsigns = $saved->getParameter('callsigns');
    if ($callsigns) {
      $query->withCallsigns($callsigns);
    }

    $status = $saved->getParameter('status');
    $status = idx($this->getStatusValues(), $status);
    if ($status) {
      $query->withStatus($status);
    }

    $order = $saved->getParameter('order');
    $order = idx($this->getOrderValues(), $order);
    if ($order) {
      $query->setOrder($order);
    } else {
      $query->setOrder(head($this->getOrderValues()));
    }

    $types = $saved->getParameter('types');
    if ($types) {
      $query->withTypes($types);
    }

    $name = $saved->getParameter('name');
    if (strlen($name)) {
      $query->withNameContains($name);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $callsigns = $saved_query->getParameter('callsigns', array());
    $types = $saved_query->getParameter('types', array());
    $types = array_fuse($types);
    $name = $saved_query->getParameter('name');

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('callsigns')
          ->setLabel(pht('Callsigns'))
          ->setValue(implode(', ', $callsigns)))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name Contains'))
          ->setValue($name))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('status')
          ->setLabel(pht('Status'))
          ->setValue($saved_query->getParameter('status'))
          ->setOptions($this->getStatusOptions()));

    $type_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Types'));

    $all_types = PhabricatorRepositoryType::getAllRepositoryTypes();
    foreach ($all_types as $key => $name) {
      $type_control->addCheckbox(
        'types[]',
        $key,
        $name,
        isset($types[$key]));
    }

    $form
      ->appendChild($type_control)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('order')
          ->setLabel(pht('Order'))
          ->setValue($saved_query->getParameter('order'))
          ->setOptions($this->getOrderOptions()));
  }

  protected function getURI($path) {
    return '/diffusion/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active Repositories'),
      'all' => pht('All Repositories'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter('status', 'open');
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      '' => pht('Active and Inactive Repositories'),
      'open' => pht('Active Repositories'),
      'closed' => pht('Inactive Repositories'),
    );
  }

  private function getStatusValues() {
    return array(
      '' => PhabricatorRepositoryQuery::STATUS_ALL,
      'open' => PhabricatorRepositoryQuery::STATUS_OPEN,
      'closed' => PhabricatorRepositoryQuery::STATUS_CLOSED,
    );
  }

  private function getOrderOptions() {
    return array(
      'committed' => pht('Most Recent Commit'),
      'name' => pht('Name'),
      'callsign' => pht('Callsign'),
      'created' => pht('Date Created'),
    );
  }

  private function getOrderValues() {
    return array(
      'committed' => PhabricatorRepositoryQuery::ORDER_COMMITTED,
      'name' => PhabricatorRepositoryQuery::ORDER_NAME,
      'callsign' => PhabricatorRepositoryQuery::ORDER_CALLSIGN,
      'created' => PhabricatorRepositoryQuery::ORDER_CREATED,
    );
  }


}
