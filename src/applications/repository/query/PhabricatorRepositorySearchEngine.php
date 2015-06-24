<?php

final class PhabricatorRepositorySearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Repositories');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function newQuery() {
    return id(new PhabricatorRepositoryQuery())
      ->needProjectPHIDs(true)
      ->needCommitCounts(true)
      ->needMostRecentCommits(true);
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Callsigns'))
        ->setKey('callsigns'),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('name'),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Status'))
        ->setKey('status')
        ->setOptions($this->getStatusOptions()),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Hosted'))
        ->setKey('hosted')
        ->setOptions($this->getHostedOptions()),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Types'))
        ->setKey('types')
        ->setOptions(PhabricatorRepositoryType::getAllRepositoryTypes()),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['callsigns']) {
      $query->withCallsigns($map['callsigns']);
    }

    if ($map['status']) {
      $status = idx($this->getStatusValues(), $map['status']);
      if ($status) {
        $query->withStatus($status);
      }
    }

    if ($map['hosted']) {
      $hosted = idx($this->getHostedValues(), $map['hosted']);
      if ($hosted) {
        $query->withHosted($hosted);
      }
    }

    if ($map['types']) {
      $query->withTypes($map['types']);
    }

    if (strlen($map['name'])) {
      $query->withNameContains($map['name']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/diffusion/'.$path;
  }

  protected function getBuiltinQueryNames() {
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

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    foreach ($repositories as $repository) {
      $id = $repository->getID();

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setObject($repository)
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

  protected function willUseSavedQuery(PhabricatorSavedQuery $saved) {
    $project_phids = $saved->getParameter('projectPHIDs', array());

    $old = $saved->getParameter('projects', array());
    foreach ($old as $phid) {
      $project_phids[] = $phid;
    }

    $any = $saved->getParameter('anyProjectPHIDs', array());
    foreach ($any as $project) {
      $project_phids[] = 'any('.$project.')';
    }

    $saved->setParameter('projectPHIDs', $project_phids);
  }

}
