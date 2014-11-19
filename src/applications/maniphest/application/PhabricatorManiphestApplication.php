<?php

final class PhabricatorManiphestApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Maniphest');
  }

  public function getShortDescription() {
    return pht('Tasks and Bugs');
  }

  public function getBaseURI() {
    return '/maniphest/';
  }

  public function getIconName() {
    return 'maniphest';
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return true;
  }

  public function getApplicationOrder() {
    return 0.110;
  }

  public function getFactObjectsForAnalysis() {
    return array(
      new ManiphestTask(),
    );
  }

  public function getEventListeners() {
    return array(
      new ManiphestNameIndexEventListener(),
      new ManiphestActionMenuEventListener(),
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
          'descriptionpreview/'
            => 'PhabricatorMarkupPreviewController',
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

    if (!$user->isLoggedIn()) {
      return $status;
    }

    $query = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withStatuses(ManiphestTaskStatus::getOpenStatusConstants())
      ->withOwners(array($user->getPHID()));
    $count = count($query->execute());

    $type = PhabricatorApplicationStatusView::TYPE_WARNING;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%s Assigned Task(s)', new PhutilNumber($count)))
      ->setCount($count);

    return $status;
  }

  public function getQuickCreateItems(PhabricatorUser $viewer) {
    $items = array();

    $item = id(new PHUIListItemView())
      ->setName(pht('Maniphest Task'))
      ->setIcon('fa-anchor')
      ->setHref($this->getBaseURI().'task/create/');
    $items[] = $item;

    return $items;
  }

  protected function getCustomCapabilities() {
    return array(
      ManiphestDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created tasks.'),
      ),
      ManiphestDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created tasks.'),
      ),
      ManiphestEditStatusCapability::CAPABILITY => array(),
      ManiphestEditAssignCapability::CAPABILITY => array(),
      ManiphestEditPoliciesCapability::CAPABILITY => array(),
      ManiphestEditPriorityCapability::CAPABILITY => array(),
      ManiphestEditProjectsCapability::CAPABILITY => array(),
      ManiphestBulkEditCapability::CAPABILITY => array(),
    );
  }

}
