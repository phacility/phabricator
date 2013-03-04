<?php

final class PhabricatorApplicationAudit extends PhabricatorApplication {

  public function getShortDescription() {
    return pht('Audit Code');
  }

  public function getBaseURI() {
    return '/audit/';
  }

  public function getIconName() {
    return 'audit';
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Audit_User_Guide.html');
  }

  public function getEventListeners() {
    return array(
      new AuditPeopleMenuEventListener()
    );
  }

  public function getRoutes() {
    return array(
      '/audit/' => array(
        '' => 'PhabricatorAuditListController',
        'view/(?P<filter>[^/]+)/(?:(?P<name>[^/]+)/)?'
          => 'PhabricatorAuditListController',
        'addcomment/' => 'PhabricatorAuditAddCommentController',
        'preview/(?P<id>[1-9]\d*)/' => 'PhabricatorAuditPreviewController',
      ),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_CORE;
  }

  public function getApplicationOrder() {
    return 0.130;
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    $phids = PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($user);

    $commits = id(new PhabricatorAuditCommitQuery())
      ->withAuthorPHIDs($phids)
      ->withStatus(PhabricatorAuditCommitQuery::STATUS_CONCERN)
      ->execute();

    $count = count($commits);
    $type = PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Problem Commit(s)', $count))
      ->setCount($count);

    $audits = id(new PhabricatorAuditQuery())
      ->withAuditorPHIDs($phids)
      ->withStatus(PhabricatorAuditQuery::STATUS_OPEN)
      ->withAwaitingUser($user)
      ->execute();

    $count = count($audits);
    $type = PhabricatorApplicationStatusView::TYPE_WARNING;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Commit(s) Awaiting Audit', $count))
      ->setCount($count);

    return $status;
  }

}

