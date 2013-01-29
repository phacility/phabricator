<?php

final class PhabricatorApplicationMetaMTA extends PhabricatorApplication {

  public function getBaseURI() {
    return '/mail/';
  }

  public function getShortDescription() {
    return pht('View Mail Logs');
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

  public function getRoutes() {
    return array(
      $this->getBaseURI() => array(
        '' => 'PhabricatorMetaMTAListController',
        'send/' => 'PhabricatorMetaMTASendController',
        'view/(?P<id>[1-9]\d*)/' => 'PhabricatorMetaMTAViewController',
        'receive/' => 'PhabricatorMetaMTAReceiveController',
        'received/' => 'PhabricatorMetaMTAReceivedListController',
        'sendgrid/' => 'PhabricatorMetaMTASendGridReceiveController',
      ),
    );
  }

  public function getTitleGlyph() {
    return '@';
  }

}
