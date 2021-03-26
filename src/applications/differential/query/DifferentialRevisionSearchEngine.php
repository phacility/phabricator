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

    if ($map['createdStart'] || $map['createdEnd']) {
      $query->withCreatedEpochBetween(
        $map['createdStart'],
        $map['createdEnd']);
    }

    if ($map['modifiedStart'] || $map['modifiedEnd']) {
      $query->withUpdatedEpochBetween(
        $map['modifiedStart'],
        $map['modifiedEnd']);
    }

    if ($map['affectedPaths']) {
      $query->withPaths($map['affectedPaths']);
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
        ->setDatasource(new DifferentialReviewerFunctionDatasource())
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
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created After'))
        ->setKey('createdStart')
        ->setDescription(
          pht('Find revisions created at or after a particular time.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created Before'))
        ->setKey('createdEnd')
        ->setDescription(
          pht('Find revisions created at or before a particular time.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Modified After'))
        ->setKey('modifiedStart')
        ->setIsHidden(true)
        ->setDescription(
          pht('Find revisions modified at or after a particular time.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Modified Before'))
        ->setKey('modifiedEnd')
        ->setIsHidden(true)
        ->setDescription(
          pht('Find revisions modified at or before a particular time.')),
      id(new PhabricatorSearchStringListField())
        ->setKey('affectedPaths')
        ->setLabel(pht('Affected Paths'))
        ->setDescription(
          pht('Search for revisions affecting particular paths.'))
        ->setIsHidden(true),
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
      ->setViewer($viewer)
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
        ->setRevisions($revisions);
    }

    if (!$views) {
      $views[] = id(new DifferentialRevisionListView())
        ->setViewer($viewer)
        ->setNoDataString(pht('No revisions found.'));
    }

    foreach ($views as $view) {
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
      ->withIsOpen(true)
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

  protected function newExportFields() {
    $fields = array(
      id(new PhabricatorStringExportField())
        ->setKey('monogram')
        ->setLabel(pht('Monogram')),
      id(new PhabricatorPHIDExportField())
        ->setKey('authorPHID')
        ->setLabel(pht('Author PHID')),
      id(new PhabricatorStringExportField())
        ->setKey('author')
        ->setLabel(pht('Author')),
      id(new PhabricatorStringExportField())
        ->setKey('status')
        ->setLabel(pht('Status')),
      id(new PhabricatorStringExportField())
        ->setKey('statusName')
        ->setLabel(pht('Status Name')),
      id(new PhabricatorURIExportField())
        ->setKey('uri')
        ->setLabel(pht('URI')),
      id(new PhabricatorStringExportField())
        ->setKey('title')
        ->setLabel(pht('Title')),
      id(new PhabricatorStringExportField())
        ->setKey('summary')
        ->setLabel(pht('Summary')),
      id(new PhabricatorStringExportField())
        ->setKey('testPlan')
        ->setLabel(pht('Test Plan')),
    );

    return $fields;
  }

  protected function newExportData(array $revisions) {
    $viewer = $this->requireViewer();

    $phids = array();
    foreach ($revisions as $revision) {
      $phids[] = $revision->getAuthorPHID();
    }
    $handles = $viewer->loadHandles($phids);

    $export = array();
    foreach ($revisions as $revision) {

      $author_phid = $revision->getAuthorPHID();
      if ($author_phid) {
        $author_name = $handles[$author_phid]->getName();
      } else {
        $author_name = null;
      }

      $status = $revision->getStatusObject();
      $status_name = $status->getDisplayName();
      $status_value = $status->getKey();

      $export[] = array(
        'monogram' => $revision->getMonogram(),
        'authorPHID' => $author_phid,
        'author' => $author_name,
        'status' => $status_value,
        'statusName' => $status_name,
        'uri' => PhabricatorEnv::getProductionURI($revision->getURI()),
        'title' => (string)$revision->getTitle(),
        'summary' => (string)$revision->getSummary(),
        'testPlan' => (string)$revision->getTestPlan(),
      );
    }

    return $export;
  }

}
