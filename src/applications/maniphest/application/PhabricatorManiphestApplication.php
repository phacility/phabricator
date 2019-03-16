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

  public function getIcon() {
    return 'fa-anchor';
  }

  public function getTitleGlyph() {
    return "\xE2\x9A\x93";
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

  public function getRemarkupRules() {
    return array(
      new ManiphestRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/T(?P<id>[1-9]\d*)' => 'ManiphestTaskDetailController',
      '/maniphest/' => array(
        $this->getQueryRoutePattern() => 'ManiphestTaskListController',
        'report/(?:(?P<view>\w+)/)?' => 'ManiphestReportController',
        $this->getBulkRoutePattern('bulk/') => 'ManiphestBulkEditController',
        'task/' => array(
          $this->getEditRoutePattern('edit/')
            => 'ManiphestTaskEditController',
          'subtask/(?P<id>[1-9]\d*)/' => 'ManiphestTaskSubtaskController',
        ),
        'graph/(?P<id>[1-9]\d*)/' => 'ManiphestTaskGraphController',
      ),
    );
  }

  public function supportsEmailIntegration() {
    return true;
  }

  public function getAppEmailBlurb() {
    return pht(
      'Send email to these addresses to create tasks. %s',
      phutil_tag(
        'a',
        array(
          'href' => $this->getInboundEmailSupportLink(),
        ),
        pht('Learn More')));
  }

  protected function getCustomCapabilities() {
    return array(
      ManiphestDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created tasks.'),
        'template' => ManiphestTaskPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      ManiphestDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created tasks.'),
        'template' => ManiphestTaskPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
      ManiphestBulkEditCapability::CAPABILITY => array(),
    );
  }

  public function getMailCommandObjects() {
    return array(
      'task' => array(
        'name' => pht('Email Commands: Tasks'),
        'header' => pht('Interacting with Maniphest Tasks'),
        'object' => new ManiphestTask(),
        'summary' => pht(
          'This page documents the commands you can use to interact with '.
          'tasks in Maniphest. These commands work when creating new tasks '.
          'via email and when replying to existing tasks.'),
      ),
    );
  }

  public function getApplicationSearchDocumentTypes() {
    return array(
      ManiphestTaskPHIDType::TYPECONST,
    );
  }

}
