<?php

final class PhabricatorAuditApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/audit/';
  }

  public function getIconName() {
    return 'audit';
  }

  public function getName() {
    return pht('Audit');
  }

  public function getShortDescription() {
    return pht('Browse and Audit Commits');
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return true;
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('Audit User Guide');
  }

  public function getEventListeners() {
    return array(
      new AuditActionMenuEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/audit/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorAuditListController',
        'addcomment/' => 'PhabricatorAuditAddCommentController',
        'preview/(?P<id>[1-9]\d*)/' => 'PhabricatorAuditPreviewController',
      ),
    );
  }

  public function getApplicationOrder() {
    return 0.130;
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    $phids = PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($user);

    $query = id(new DiffusionCommitQuery())
      ->setViewer($user)
      ->withAuthorPHIDs(array($user->getPHID()))
      ->withAuditStatus(DiffusionCommitQuery::AUDIT_STATUS_CONCERN);
    $commits = $query->execute();

    $count = count($commits);
    $type = PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Problem Commit(s)', $count))
      ->setCount($count);

    $query = id(new DiffusionCommitQuery())
      ->setViewer($user)
      ->withAuditorPHIDs($phids)
      ->withAuditStatus(DiffusionCommitQuery::AUDIT_STATUS_OPEN)
      ->withAuditAwaitingUser($user);
    $commits = $query->execute();

    $count = count($commits);
    $type = PhabricatorApplicationStatusView::TYPE_WARNING;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Commit(s) Awaiting Audit', $count))
      ->setCount($count);

    return $status;
  }

}
