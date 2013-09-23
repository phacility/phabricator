<?php

final class PhabricatorApplicationManiphest extends PhabricatorApplication {

  public function getShortDescription() {
    return 'Tasks and Bugs';
  }

  public function getBaseURI() {
    return '/maniphest/';
  }

  public function getIconName() {
    return 'maniphest';
  }

  public function getApplicationGroup() {
    return self::GROUP_CORE;
  }

  public function getApplicationOrder() {
    return 0.110;
  }

  public function getFactObjectsForAnalysis() {
    return array(
      new ManiphestTask(),
    );
  }

  public function getQuickCreateURI() {
    return $this->getBaseURI().'task/create/';
  }

  public function getEventListeners() {
    return array(
      new ManiphestNameIndexEventListener(),
      new ManiphestPeopleMenuEventListener(),
      new ManiphestHovercardEventListener(),
    );
  }

  public function getRemarkupRules() {
    return array(
      new ManiphestRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/T(?P<id>[1-9]\d*)' => 'ManiphestTaskDetailController',
      '/maniphest/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'ManiphestTaskListController',
        'report/(?:(?P<view>\w+)/)?' => 'ManiphestReportController',
        'batch/' => 'ManiphestBatchEditController',
        'task/' => array(
          'create/' => 'ManiphestTaskEditController',
          'edit/(?P<id>[1-9]\d*)/' => 'ManiphestTaskEditController',
          'descriptionpreview/' =>
            'PhabricatorMarkupPreviewController',
        ),
        'transaction/' => array(
          'save/' => 'ManiphestTransactionSaveController',
          'preview/(?P<id>[1-9]\d*)/'
            => 'ManiphestTransactionPreviewController',
        ),
        'export/(?P<key>[^/]+)/' => 'ManiphestExportController',
        'subpriority/' => 'ManiphestSubpriorityController',
        'subscribe/(?P<action>add|rem)/(?P<id>[1-9]\d*)/'
        => 'ManiphestSubscribeController',
      ),
    );
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    $query = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->withOwners(array($user->getPHID()));
    $count = count($query->execute());

    $type = PhabricatorApplicationStatusView::TYPE_WARNING;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%s Assigned Task(s)', new PhutilNumber($count)))
      ->setCount($count);

    return $status;
  }

}

