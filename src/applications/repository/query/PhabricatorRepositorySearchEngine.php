<?php

final class PhabricatorRepositorySearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Repositories');
  }

  public function getApplicationClassName() {
    return 'PhabricatorApplicationDiffusion';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('callsigns', $request->getStrList('callsigns'));
    $saved->setParameter('status', $request->getStr('status'));
    $saved->setParameter('order', $request->getStr('order'));
    $saved->setParameter('hosted', $request->getStr('hosted'));
    $saved->setParameter('types', $request->getArr('types'));
    $saved->setParameter('name', $request->getStr('name'));
    $saved->setParameter('anyProjectPHIDs', $request->getArr('anyProjects'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorRepositoryQuery())
      ->needProjectPHIDs(true)
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

    $hosted = $saved->getParameter('hosted');
    $hosted = idx($this->getHostedValues(), $hosted);
    if ($hosted) {
      $query->withHosted($hosted);
    }

    $types = $saved->getParameter('types');
    if ($types) {
      $query->withTypes($types);
    }

    $name = $saved->getParameter('name');
    if (strlen($name)) {
      $query->withNameContains($name);
    }

    $any_project_phids = $saved->getParameter('anyProjectPHIDs');
    if ($any_project_phids) {
      $query->withAnyProjects($any_project_phids);
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
    $any_project_phids = $saved_query->getParameter('anyProjectPHIDs', array());

    if ($any_project_phids) {
      $any_project_handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->withPHIDs($any_project_phids)
        ->execute();
    } else {
      $any_project_handles = array();
    }

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
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/projects/')
          ->setName('anyProjects')
          ->setLabel(pht('In Any Project'))
          ->setValue($any_project_handles))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('status')
          ->setLabel(pht('Status'))
          ->setValue($saved_query->getParameter('status'))
          ->setOptions($this->getStatusOptions()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('hosted')
          ->setLabel(pht('Hosted'))
          ->setValue($saved_query->getParameter('hosted'))
          ->setOptions($this->getHostedOptions()));

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

  private function getHostedOptions() {
    return array(
      '' => pht('Hosted and Remote Repositories'),
      'phabricator' => pht('Hosted Repositories'),
      'remote' => pht('Remote Repositories'),
    );
  }

  private function getHostedValues() {
    return array(
      '' => PhabricatorRepositoryQuery::HOSTED_ALL,
      'phabricator' => PhabricatorRepositoryQuery::HOSTED_PHABRICATOR,
      'remote' => PhabricatorRepositoryQuery::HOSTED_REMOTE,
    );
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $repositories,
    PhabricatorSavedQuery $query) {
    return array_mergev(mpull($repositories, 'getProjectPHIDs'));
  }

  protected function renderResultList(
    array $repositories,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($repositories, 'PhabricatorRepository');

    $viewer = $this->requireViewer();;

    $list = new PHUIObjectItemListView();
    foreach ($repositories as $repository) {
      $id = $repository->getID();

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setHeader($repository->getName())
        ->setObjectName('r'.$repository->getCallsign())
        ->setHref($this->getApplicationURI($repository->getCallsign().'/'));

      $commit = $repository->getMostRecentCommit();
      if ($commit) {
        $commit_link = DiffusionView::linkCommit(
            $repository,
            $commit->getCommitIdentifier(),
            $commit->getSummary());
        $item->setSubhead($commit_link);
        $item->setEpoch($commit->getEpoch());
      }

      $item->addIcon(
        'none',
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()));

      $size = $repository->getCommitCount();
      if ($size) {
        $history_uri = DiffusionRequest::generateDiffusionURI(
          array(
            'callsign' => $repository->getCallsign(),
            'action' => 'history',
          ));

        $item->addAttribute(
          phutil_tag(
            'a',
            array(
              'href' => $history_uri,
            ),
            pht('%s Commit(s)', new PhutilNumber($size))));
      } else {
        $item->addAttribute(pht('No Commits'));
      }

      $project_handles = array_select_keys(
        $handles,
        $repository->getProjectPHIDs());
      if ($project_handles) {
        $item->addAttribute(
          id(new PHUIHandleTagListView())
            ->setSlim(true)
            ->setHandles($project_handles));
      }

      if (!$repository->isTracked()) {
        $item->setDisabled(true);
        $item->addIcon('disable-grey', pht('Inactive'));
      }

      $list->addItem($item);
    }

    return $list;
  }

}
