<?php

final class PhabricatorMetaMTAApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Mail');
  }

  public function getBaseURI() {
    return '/mail/';
  }

  public function getFontIcon() {
    return 'fa-send';
  }

  public function getShortDescription() {
    return pht('Send and Receive Mail');
  }

  public function getFlavorText() {
    return pht('Every program attempts to expand until it can read mail.');
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function canUninstall() {
    return false;
  }

  public function getTypeaheadURI() {
    return '/mail/';
  }

  public function getRoutes() {
    return array(
      '/mail/' => array(
        '(query/(?P<queryKey>[^/]+)/)?' =>
          'PhabricatorMetaMTAMailListController',
        'detail/(?P<id>[1-9]\d*)/' => 'PhabricatorMetaMTAMailViewController',
        'sendgrid/' => 'PhabricatorMetaMTASendGridReceiveController',
        'mailgun/'  => 'PhabricatorMetaMTAMailgunReceiveController',
      ),
    );
  }

  public function getTitleGlyph() {
    return '@';
  }

}
