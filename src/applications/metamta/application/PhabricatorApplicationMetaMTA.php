<?php

final class PhabricatorApplicationMetaMTA extends PhabricatorApplication {

  public function getBaseURI() {
    return '/mail/';
  }

  public function getIconName() {
    return 'metamta';
  }

  public function getFlavorText() {
    return pht('Yo dawg, we heard you like MTAs.');
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function canUninstall() {
    return false;
  }

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function getTypeaheadURI() {
    return null;
  }

  public function getRoutes() {
    return array(
      $this->getBaseURI() => array(
        'sendgrid/' => 'PhabricatorMetaMTASendGridReceiveController',
        'mailgun/' => 'PhabricatorMetaMTAMailgunReceiveController',
      ),
    );
  }

  public function getTitleGlyph() {
    return '@';
  }

}
