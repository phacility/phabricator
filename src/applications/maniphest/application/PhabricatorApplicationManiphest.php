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
      ->setAppIcon('maniphest-dark')
      ->setHref($this->getBaseURI().'task/create/');
    $items[] = $item;

    return $items;
  }

  protected function getCustomCapabilities() {
    return array(
      ManiphestCapabilityDefaultView::CAPABILITY => array(
        'caption' => pht(
          'Default view policy for newly created tasks.'),
      ),
      ManiphestCapabilityDefaultEdit::CAPABILITY => array(
        'caption' => pht(
          'Default edit policy for newly created tasks.'),
      ),
      ManiphestCapabilityEditStatus::CAPABILITY => array(
      ),
      ManiphestCapabilityEditAssign::CAPABILITY => array(
      ),
      ManiphestCapabilityEditPolicies::CAPABILITY => array(
      ),
      ManiphestCapabilityEditPriority::CAPABILITY => array(
      ),
      ManiphestCapabilityEditProjects::CAPABILITY => array(
      ),
      ManiphestCapabilityBulkEdit::CAPABILITY => array(
      ),
    );
  }

}
