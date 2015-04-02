<?php

final class PhabricatorCommitSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Commits');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'auditorPHIDs',
      $this->readPHIDsFromRequest($request, 'auditorPHIDs'));

    $saved->setParameter(
      'commitAuthorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter(
      'auditStatus',
      $request->getStr('auditStatus'));

    $saved->setParameter(
      'repositoryPHIDs',
      $this->readPHIDsFromRequest($request, 'repositoryPHIDs'));

    // -- TODO - T4173 - file location

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new DiffusionCommitQuery())
      ->needAuditRequests(true)
      ->needCommitData(true);

    $auditor_phids = $saved->getParameter('auditorPHIDs', array());
    if ($auditor_phids) {
      $query->withAuditorPHIDs($auditor_phids);
    }

    $commit_author_phids = $saved->getParameter('commitAuthorPHIDs', array());
    if ($commit_author_phids) {
      $query->withAuthorPHIDs($commit_author_phids);
    }

    $audit_status = $saved->getParameter('auditStatus', null);
    if ($audit_status) {
      $query->withAuditStatus($audit_status);
    }

    $awaiting_user_phid = $saved->getParameter('awaitingUserPHID', null);
    if ($awaiting_user_phid) {
      // This is used only for the built-in "needs attention" filter,
      // so cheat and just use the already-loaded viewer rather than reloading
      // it.
      $query->withAuditAwaitingUser($this->requireViewer());
    }

    $repository_phids = $saved->getParameter('repositoryPHIDs', array());
    if ($repository_phids) {
      $query->withRepositoryPHIDs($repository_phids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $auditor_phids = $saved->getParameter('auditorPHIDs', array());
    $commit_author_phids = $saved->getParameter(
      'commitAuthorPHIDs',
      array());
    $audit_status = $saved->getParameter('auditStatus', null);
    $repository_phids = $saved->getParameter('repositoryPHIDs', array());

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new DiffusionAuditorDatasource())
          ->setName('auditorPHIDs')
          ->setLabel(pht('Auditors'))
          ->setValue($auditor_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('authors')
          ->setLabel(pht('Commit Authors'))
          ->setValue($commit_author_phids))
       ->appendChild(
         id(new AphrontFormSelectControl())
           ->setName('auditStatus')
           ->setLabel(pht('Audit Status'))
           ->setOptions($this->getAuditStatusOptions())
           ->setValue($audit_status))
       ->appendControl(
         id(new AphrontFormTokenizerControl())
           ->setLabel(pht('Repositories'))
           ->setName('repositoryPHIDs')
           ->setDatasource(new DiffusionRepositoryDatasource())
           ->setValue($repository_phids));

  }

  protected function getURI($path) {
    return '/audit/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['need'] = pht('Need Attention');
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

    switch ($query_key) {
      case 'all':
        return $query;
      case 'open':
        $query->setParameter(
          'auditStatus',
          DiffusionCommitQuery::AUDIT_STATUS_OPEN);
        return $query;
      case 'need':
        $query->setParameter('awaitingUserPHID', $viewer->getPHID());
        $query->setParameter(
          'auditStatus',
          DiffusionCommitQuery::AUDIT_STATUS_OPEN);
        $query->setParameter(
          'auditorPHIDs',
          PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($viewer));
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

    return $view->buildList();
  }

}
