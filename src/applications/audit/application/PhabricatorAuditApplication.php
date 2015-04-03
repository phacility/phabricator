<?php

final class PhabricatorAuditApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/audit/';
  }

  public function getFontIcon() {
    return 'fa-check-circle-o';
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

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Audit User Guide'),
        'href' => PhabricatorEnv::getDoclink('Audit User Guide'),
      ),
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
      ->withAuditStatus(DiffusionCommitQuery::AUDIT_STATUS_CONCERN)
      ->setLimit(self::MAX_STATUS_ITEMS);
    $commits = $query->execute();

    $count = count($commits);
    $count_str = self::formatStatusCount(
      $count,
      '%s Problem Commits',
      '%d Problem Commit(s)');
    $type = PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText($count_str)
      ->setCount($count);

    $query = id(new DiffusionCommitQuery())
      ->setViewer($user)
      ->withAuditorPHIDs($phids)
      ->withAuditStatus(DiffusionCommitQuery::AUDIT_STATUS_OPEN)
      ->withAuditAwaitingUser($user)
      ->setLimit(self::MAX_STATUS_ITEMS);
    $commits = $query->execute();

    $count = count($commits);
    $count_str = self::formatStatusCount(
      $count,
      '%s Commits Awaiting Audit',
      '%d Commit(s) Awaiting Audit');
    $type = PhabricatorApplicationStatusView::TYPE_WARNING;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText($count_str)
      ->setCount($count);

    return $status;
  }

}
