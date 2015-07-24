<?php

final class PhabricatorCountdownApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/countdown/';
  }

  public function getFontIcon() {
    return 'fa-rocket';
  }

  public function getName() {
    return pht('Countdown');
  }

  public function getShortDescription() {
    return pht('Countdown to Events');
  }

  public function getTitleGlyph() {
    return "\xE2\x9A\xB2";
  }

  public function getFlavorText() {
    return pht('Utilize the full capabilities of your ALU.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorCountdownRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/C(?P<id>[1-9]\d*)' => 'PhabricatorCountdownViewController',
      '/countdown/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorCountdownListController',
        '(?P<id>[1-9]\d*)/'
          => 'PhabricatorCountdownViewController',
        'comment/(?P<id>[1-9]\d*)/'
          => 'PhabricatorCountdownCommentController',
        'edit/(?:(?P<id>[1-9]\d*)/)?'
          => 'PhabricatorCountdownEditController',
        'delete/(?P<id>[1-9]\d*)/'
          => 'PhabricatorCountdownDeleteController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhabricatorCountdownDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for new countdowns.'),
        'template' => PhabricatorCountdownCountdownPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      PhabricatorCountdownDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for new countdowns.'),
        'template' => PhabricatorCountdownCountdownPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
    );
  }

}
