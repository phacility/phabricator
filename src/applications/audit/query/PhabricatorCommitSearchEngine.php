<?php

final class PhabricatorCommitSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Diffusion Commits');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function newQuery() {
    return id(new DiffusionCommitQuery())
      ->needAuditRequests(true)
      ->needCommitData(true)
      ->needDrafts(true);
  }

  protected function newResultBuckets() {
    return DiffusionCommitResultBucket::getAllResultBuckets();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['responsiblePHIDs']) {
      $query->withResponsiblePHIDs($map['responsiblePHIDs']);
    }

    if ($map['auditorPHIDs']) {
      $query->withAuditorPHIDs($map['auditorPHIDs']);
    }

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    if ($map['repositoryPHIDs']) {
      $query->withRepositoryPHIDs($map['repositoryPHIDs']);
    }

    if ($map['packagePHIDs']) {
      $query->withPackagePHIDs($map['packagePHIDs']);
    }

    if ($map['unreachable'] !== null) {
      $query->withUnreachable($map['unreachable']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Responsible Users'))
        ->setKey('responsiblePHIDs')
        ->setConduitKey('responsible')
        ->setAliases(array('responsible', 'responsibles', 'responsiblePHID'))
        ->setDatasource(new DifferentialResponsibleDatasource()),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Authors'))
        ->setKey('authorPHIDs')
        ->setConduitKey('authors')
        ->setAliases(array('author', 'authors', 'authorPHID')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Auditors'))
        ->setKey('auditorPHIDs')
        ->setConduitKey('auditors')
        ->setAliases(array('auditor', 'auditors', 'auditorPHID'))
        ->setDatasource(new DiffusionAuditorFunctionDatasource()),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Audit Status'))
        ->setKey('statuses')
        ->setAliases(array('status'))
        ->setOptions(PhabricatorAuditCommitStatusConstants::getStatusNameMap()),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Repositories'))
        ->setKey('repositoryPHIDs')
        ->setConduitKey('repositories')
        ->setAliases(array('repository', 'repositories', 'repositoryPHID'))
        ->setDatasource(new DiffusionRepositoryFunctionDatasource()),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Packages'))
        ->setKey('packagePHIDs')
        ->setConduitKey('packages')
        ->setAliases(array('package', 'packages', 'packagePHID'))
        ->setDatasource(new PhabricatorOwnersPackageDatasource()),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Unreachable'))
        ->setKey('unreachable')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Unreachable Commits'),
          pht('Hide Unreachable Commits'))
        ->setDescription(
          pht(
            'Find or exclude unreachable commits which are not ancestors of '.
            'any branch, tag, or ref.')),
    );
  }

  protected function getURI($path) {
    return '/diffusion/commit/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['active'] = pht('Active Audits');
      $names['authored'] = pht('Authored');
      $names['audited'] = pht('Audited');
    }

    $names['all'] = pht('All Commits');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);
    $viewer = $this->requireViewer();

    $viewer_phid = $viewer->getPHID();
    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        $bucket_key = DiffusionCommitRequiredActionResultBucket::BUCKETKEY;

        $open = PhabricatorAuditCommitStatusConstants::getOpenStatusConstants();

        $query
          ->setParameter('responsiblePHIDs', array($viewer_phid))
          ->setParameter('statuses', $open)
          ->setParameter('bucket', $bucket_key)
          ->setParameter('unreachable', false);
        return $query;
      case 'authored':
        $query
          ->setParameter('authorPHIDs', array($viewer_phid));
        return $query;
      case 'audited':
        $query
          ->setParameter('auditorPHIDs', array($viewer_phid));
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $commits,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $viewer = $this->requireViewer();

    $bucket = $this->getResultBucket($query);

    $template = id(new PhabricatorAuditListView())
      ->setViewer($viewer)
      ->setShowDrafts(true);

    $views = array();
    if ($bucket) {
      $bucket->setViewer($viewer);

      try {
        $groups = $bucket->newResultGroups($query, $commits);

        foreach ($groups as $group) {
          $views[] = id(clone $template)
            ->setHeader($group->getName())
            ->setNoDataString($group->getNoDataString())
            ->setCommits($group->getObjects());
        }
      } catch (Exception $ex) {
        $this->addError($ex->getMessage());
      }
    } else {
      $views[] = id(clone $template)
        ->setCommits($commits)
        ->setNoDataString(pht('No matching commits.'));
    }

    if (count($views) == 1) {
      $list = head($views)->buildList();
    } else {
      $list = $views;
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setContent($list);

    return $result;
  }

  protected function getNewUserBody() {

    $view = id(new PHUIBigInfoView())
      ->setIcon('fa-check-circle-o')
      ->setTitle(pht('Welcome to Audit'))
      ->setDescription(
        pht('Post-commit code review and auditing. Audits you are assigned '.
            'to will appear here.'));

      return $view;
  }

}
