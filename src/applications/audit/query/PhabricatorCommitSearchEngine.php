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
      ->needIdentities(true)
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

    if ($map['permanent'] !== null) {
      $query->withPermanent($map['permanent']);
    }

    if ($map['ancestorsOf']) {
      $query->withAncestorsOf($map['ancestorsOf']);
    }

    if ($map['identifiers']) {
      $query->withIdentifiers($map['identifiers']);
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
        ->setDatasource(new DifferentialResponsibleDatasource())
        ->setDescription(
          pht(
            'Find commits where given users, projects, or packages are '.
            'responsible for the next steps in the audit workflow.')),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Authors'))
        ->setKey('authorPHIDs')
        ->setConduitKey('authors')
        ->setAliases(array('author', 'authors', 'authorPHID'))
        ->setDescription(pht('Find commits authored by particular users.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Auditors'))
        ->setKey('auditorPHIDs')
        ->setConduitKey('auditors')
        ->setAliases(array('auditor', 'auditors', 'auditorPHID'))
        ->setDatasource(new DiffusionAuditorFunctionDatasource())
        ->setDescription(
          pht(
            'Find commits where given users, projects, or packages are '.
            'auditors.')),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Audit Status'))
        ->setKey('statuses')
        ->setAliases(array('status'))
        ->setOptions(DiffusionCommitAuditStatus::newOptions())
        ->setDeprecatedOptions(
          DiffusionCommitAuditStatus::newDeprecatedOptions())
        ->setDescription(pht('Find commits with given audit statuses.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Repositories'))
        ->setKey('repositoryPHIDs')
        ->setConduitKey('repositories')
        ->setAliases(array('repository', 'repositories', 'repositoryPHID'))
        ->setDatasource(new DiffusionRepositoryFunctionDatasource())
        ->setDescription(pht('Find commits in particular repositories.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Packages'))
        ->setKey('packagePHIDs')
        ->setConduitKey('packages')
        ->setAliases(array('package', 'packages', 'packagePHID'))
        ->setDatasource(new PhabricatorOwnersPackageDatasource())
        ->setDescription(
          pht('Find commits which affect given packages.')),
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
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Permanent'))
        ->setKey('permanent')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Permanent Commits'),
          pht('Hide Permanent Commits'))
        ->setDescription(
          pht(
            'Find or exclude permanent commits which are ancestors of '.
            'any permanent branch, tag, or ref.')),
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Ancestors Of'))
        ->setKey('ancestorsOf')
        ->setDescription(
          pht(
            'Find commits which are ancestors of a particular ref, '.
            'like "master".')),
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Identifiers'))
        ->setKey('identifiers')
        ->setDescription(
          pht(
            'Find commits with particular identifiers (usually, hashes). '.
            'Supports full or partial identifiers (like "abcd12340987..." or '.
            '"abcd1234") and qualified or unqualified identifiers (like '.
            '"rXabcd1234" or "abcd1234").')),
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

        $open = DiffusionCommitAuditStatus::getOpenStatusConstants();

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

    $template = id(new DiffusionCommitGraphView())
      ->setViewer($viewer)
      ->setShowAuditors(true);

    $views = array();
    if ($bucket) {
      $bucket->setViewer($viewer);

      try {
        $groups = $bucket->newResultGroups($query, $commits);

        foreach ($groups as $group) {
          // Don't show groups in Dashboard Panels
          if ($group->getObjects() || !$this->isPanelContext()) {
            $item_list = id(clone $template)
              ->setCommits($group->getObjects())
              ->newObjectItemListView();

            $views[] = $item_list
              ->setHeader($group->getName())
              ->setNoDataString($group->getNoDataString());
          }
        }
      } catch (Exception $ex) {
        $this->addError($ex->getMessage());
      }
    }

    if (!$views) {
      $item_list = id(clone $template)
        ->setCommits($commits)
        ->newObjectItemListView();

      $views[] = $item_list
        ->setNoDataString(pht('No commits found.'));
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setContent($views);
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
