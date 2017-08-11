<?php

final class DifferentialRevisionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Differential Revisions');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDifferentialApplication';
  }

  protected function newResultBuckets() {
    return DifferentialRevisionResultBucket::getAllResultBuckets();
  }

  public function newQuery() {
    return id(new DifferentialRevisionQuery())
      ->needFlags(true)
      ->needDrafts(true)
      ->needReviewers(true);
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['responsiblePHIDs']) {
      $query->withResponsibleUsers($map['responsiblePHIDs']);
    }

    if ($map['authorPHIDs']) {
      $query->withAuthors($map['authorPHIDs']);
    }

    if ($map['reviewerPHIDs']) {
      $query->withReviewers($map['reviewerPHIDs']);
    }

    if ($map['repositoryPHIDs']) {
      $query->withRepositoryPHIDs($map['repositoryPHIDs']);
    }

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Responsible Users'))
        ->setKey('responsiblePHIDs')
        ->setAliases(array('responsiblePHID', 'responsibles', 'responsible'))
        ->setDatasource(new DifferentialResponsibleDatasource())
        ->setDescription(
          pht('Find revisions that a given user is responsible for.')),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Authors'))
        ->setKey('authorPHIDs')
        ->setAliases(array('author', 'authors', 'authorPHID'))
        ->setDescription(
          pht('Find revisions with specific authors.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Reviewers'))
        ->setKey('reviewerPHIDs')
        ->setAliases(array('reviewer', 'reviewers', 'reviewerPHID'))
        ->setDatasource(new DiffusionAuditorFunctionDatasource())
        ->setDescription(
          pht('Find revisions with specific reviewers.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Repositories'))
        ->setKey('repositoryPHIDs')
        ->setAliases(array('repository', 'repositories', 'repositoryPHID'))
        ->setDatasource(new DiffusionRepositoryFunctionDatasource())
        ->setDescription(
          pht('Find revisions from specific repositories.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Statuses'))
        ->setKey('statuses')
        ->setAliases(array('status'))
        ->setDatasource(new DifferentialRevisionStatusFunctionDatasource())
        ->setDescription(
          pht('Find revisions with particular statuses.')),
    );
  }

  protected function getURI($path) {
    return '/differential/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['active'] = pht('Active Revisions');
      $names['authored'] = pht('Authored');
    }

    $names['all'] = pht('All Revisions');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer = $this->requireViewer();

    switch ($query_key) {
      case 'active':
        $bucket_key = DifferentialRevisionRequiredActionResultBucket::BUCKETKEY;

        return $query
          ->setParameter('responsiblePHIDs', array($viewer->getPHID()))
          ->setParameter('statuses', array('open()'))
          ->setParameter('bucket', $bucket_key);
      case 'authored':
        return $query
          ->setParameter('authorPHIDs', array($viewer->getPHID()));
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      DifferentialLegacyQuery::STATUS_ANY            => pht('All'),
      DifferentialLegacyQuery::STATUS_OPEN           => pht('Open'),
      DifferentialLegacyQuery::STATUS_ACCEPTED       => pht('Accepted'),
      DifferentialLegacyQuery::STATUS_NEEDS_REVIEW   => pht('Needs Review'),
      DifferentialLegacyQuery::STATUS_NEEDS_REVISION => pht('Needs Revision'),
      DifferentialLegacyQuery::STATUS_CLOSED         => pht('Closed'),
      DifferentialLegacyQuery::STATUS_ABANDONED      => pht('Abandoned'),
    );
  }

  protected function renderResultList(
    array $revisions,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($revisions, 'DifferentialRevision');

    $viewer = $this->requireViewer();
    $template = id(new DifferentialRevisionListView())
      ->setUser($viewer)
      ->setNoBox($this->isPanelContext());

    $bucket = $this->getResultBucket($query);

    $unlanded = $this->loadUnlandedDependencies($revisions);

    $views = array();
    if ($bucket) {
      $bucket->setViewer($viewer);

      try {
        $groups = $bucket->newResultGroups($query, $revisions);

        foreach ($groups as $group) {
          // Don't show groups in Dashboard Panels
          if ($group->getObjects() || !$this->isPanelContext()) {
            $views[] = id(clone $template)
              ->setHeader($group->getName())
              ->setNoDataString($group->getNoDataString())
              ->setRevisions($group->getObjects());
          }
        }
      } catch (Exception $ex) {
        $this->addError($ex->getMessage());
      }
    } else {
      $views[] = id(clone $template)
        ->setRevisions($revisions)
        ->setHandles(array());
    }

    if (!$views) {
      $views[] = id(new DifferentialRevisionListView())
        ->setUser($viewer)
        ->setNoDataString(pht('No revisions found.'));
    }

    $phids = array_mergev(mpull($views, 'getRequiredHandlePHIDs'));
    if ($phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($phids)
        ->execute();
    } else {
      $handles = array();
    }

    foreach ($views as $view) {
      $view->setHandles($handles);
      $view->setUnlandedDependencies($unlanded);
    }

    if (count($views) == 1) {
      // Reduce this to a PHUIObjectItemListView so we can get the free
      // support from ApplicationSearch.
      $list = head($views)->render();
    } else {
      $list = $views;
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setContent($list);

    return $result;
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Diff'))
      ->setHref('/differential/diff/create/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Pre-commit code review. Revisions that are waiting on your input '.
            'will appear here.'))
      ->addAction($create_button);

      return $view;
  }

  private function loadUnlandedDependencies(array $revisions) {
    $phids = array();
    foreach ($revisions as $revision) {
      if (!$revision->isAccepted()) {
        continue;
      }

      $phids[] = $revision->getPHID();
    }

    if (!$phids) {
      return array();
    }

    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($phids)
      ->withEdgeTypes(
        array(
          DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST,
        ));

    $query->execute();

    $revision_phids = $query->getDestinationPHIDs();
    if (!$revision_phids) {
      return array();
    }

    $viewer = $this->requireViewer();

    $blocking_revisions = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withPHIDs($revision_phids)
      ->withStatus(DifferentialLegacyQuery::STATUS_OPEN)
      ->execute();
    $blocking_revisions = mpull($blocking_revisions, null, 'getPHID');

    $result = array();
    foreach ($revisions as $revision) {
      $revision_phid = $revision->getPHID();
      $blocking_phids = $query->getDestinationPHIDs(array($revision_phid));
      $blocking = array_select_keys($blocking_revisions, $blocking_phids);
      if ($blocking) {
        $result[$revision_phid] = $blocking;
      }
    }

    return $result;
  }

}
