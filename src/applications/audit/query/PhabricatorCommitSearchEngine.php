<?php

final class PhabricatorCommitSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Commits');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function newQuery() {
    return id(new DiffusionCommitQuery())
      ->needAuditRequests(true)
      ->needCommitData(true);
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['needsAuditByPHIDs']) {
      $query->withNeedsAuditByPHIDs($map['needsAuditByPHIDs']);
    }

    if ($map['auditorPHIDs']) {
      $query->withAuditorPHIDs($map['auditorPHIDs']);
    }

    if ($map['commitAuthorPHIDs']) {
      $query->withAuthorPHIDs($map['commitAuthorPHIDs']);
    }

    if ($map['auditStatus']) {
      $query->withAuditStatus($map['auditStatus']);
    }

    if ($map['repositoryPHIDs']) {
      $query->withRepositoryPHIDs($map['repositoryPHIDs']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Needs Audit By'))
        ->setKey('needsAuditByPHIDs')
        ->setAliases(array('needs', 'need'))
        ->setDatasource(new DiffusionAuditorFunctionDatasource()),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Auditors'))
        ->setKey('auditorPHIDs')
        ->setAliases(array('auditor', 'auditors'))
        ->setDatasource(new DiffusionAuditorFunctionDatasource()),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Authors'))
        ->setKey('commitAuthorPHIDs')
        ->setAliases(array('author', 'authors')),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Audit Status'))
        ->setKey('auditStatus')
        ->setAliases(array('status'))
        ->setOptions($this->getAuditStatusOptions()),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Repositories'))
        ->setKey('repositoryPHIDs')
        ->setAliases(array('repository', 'repositories'))
        ->setDatasource(new DiffusionRepositoryDatasource()),
    );
  }

  protected function getURI($path) {
    return '/audit/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['need'] = pht('Needs Audit');
      $names['problem'] = pht('Problem Commits');
    }

    $names['open'] = pht('Open Audits');

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored Commits');
    }

    $names['all'] = pht('All Commits');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);
    $viewer = $this->requireViewer();

    $viewer_phid = $viewer->getPHID();
    $status_open = DiffusionCommitQuery::AUDIT_STATUS_OPEN;

    switch ($query_key) {
      case 'all':
        return $query;
      case 'open':
        $query->setParameter('auditStatus', $status_open);
        return $query;
      case 'need':
        $needs_tokens = array(
          $viewer_phid,
          'projects('.$viewer_phid.')',
          'packages('.$viewer_phid.')',
        );

        $query->setParameter('needsAuditByPHIDs', $needs_tokens);
        $query->setParameter('auditStatus', $status_open);
        return $query;
      case 'authored':
        $query->setParameter('commitAuthorPHIDs', array($viewer->getPHID()));
        return $query;
      case 'problem':
        $query->setParameter('commitAuthorPHIDs', array($viewer->getPHID()));
        $query->setParameter(
          'auditStatus',
          DiffusionCommitQuery::AUDIT_STATUS_CONCERN);
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getAuditStatusOptions() {
    return array(
      DiffusionCommitQuery::AUDIT_STATUS_ANY => pht('Any'),
      DiffusionCommitQuery::AUDIT_STATUS_OPEN => pht('Open'),
      DiffusionCommitQuery::AUDIT_STATUS_CONCERN => pht('Concern Raised'),
      DiffusionCommitQuery::AUDIT_STATUS_ACCEPTED => pht('Accepted'),
      DiffusionCommitQuery::AUDIT_STATUS_PARTIAL => pht('Partially Audited'),
    );
  }

  protected function renderResultList(
    array $commits,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($commits, 'PhabricatorRepositoryCommit');

    $viewer = $this->requireViewer();
    $nodata = pht('No matching audits.');
    $view = id(new PhabricatorAuditListView())
      ->setUser($viewer)
      ->setCommits($commits)
      ->setAuthorityPHIDs(
        PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($viewer))
      ->setNoDataString($nodata);

    $phids = $view->getRequiredHandlePHIDs();
    if ($phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($phids)
        ->execute();
    } else {
      $handles = array();
    }

    $view->setHandles($handles);
    $list = $view->buildList();

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
