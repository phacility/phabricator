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
      ->needRelationships(true);
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

    if ($map['status']) {
      $query->withStatus($map['status']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Responsible Users'))
        ->setKey('responsiblePHIDs')
        ->setAliases(array('responsiblePHID', 'responsibles', 'responsible'))
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
        ->setDatasource(new DiffusionAuditorDatasource())
        ->setDescription(
          pht('Find revisions with specific reviewers.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Repositories'))
        ->setKey('repositoryPHIDs')
        ->setAliases(array('repository', 'repositories', 'repositoryPHID'))
        ->setDatasource(new DiffusionRepositoryDatasource())
        ->setDescription(
          pht('Find revisions from specific repositories.')),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Status'))
        ->setKey('status')
        ->setOptions($this->getStatusOptions())
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
          ->setParameter('status', DifferentialRevisionQuery::STATUS_OPEN)
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
      DifferentialRevisionQuery::STATUS_ANY            => pht('All'),
      DifferentialRevisionQuery::STATUS_OPEN           => pht('Open'),
      DifferentialRevisionQuery::STATUS_ACCEPTED       => pht('Accepted'),
      DifferentialRevisionQuery::STATUS_NEEDS_REVIEW   => pht('Needs Review'),
      DifferentialRevisionQuery::STATUS_NEEDS_REVISION => pht('Needs Revision'),
      DifferentialRevisionQuery::STATUS_CLOSED         => pht('Closed'),
      DifferentialRevisionQuery::STATUS_ABANDONED      => pht('Abandoned'),
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

    $views = array();
    if ($bucket) {
        $split = DifferentialRevisionQuery::splitResponsible(
          $revisions,
          $query->getParameter('responsiblePHIDs'));
        list($blocking, $active, $waiting) = $split;

      $views[] = id(clone $template)
        ->setHeader(pht('Blocking Others'))
        ->setNoDataString(
          pht('No revisions are blocked on your action.'))
        ->setHighlightAge(true)
        ->setRevisions($blocking)
        ->setHandles(array());

      $views[] = id(clone $template)
        ->setHeader(pht('Action Required'))
        ->setNoDataString(
          pht('No revisions require your action.'))
        ->setHighlightAge(true)
        ->setRevisions($active)
        ->setHandles(array());

      $views[] = id(clone $template)
        ->setHeader(pht('Waiting on Others'))
        ->setNoDataString(
          pht('You have no revisions waiting on others.'))
        ->setRevisions($waiting)
        ->setHandles(array());
    } else {
      $views[] = id(clone $template)
        ->setRevisions($revisions)
        ->setHandles(array());
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

}
