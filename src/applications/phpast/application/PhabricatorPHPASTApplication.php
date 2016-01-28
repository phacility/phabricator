<?php

final class PhabricatorPHPASTApplication extends PhabricatorApplication {

  public function getName() {
    return pht('PHPAST');
  }

  public function getBaseURI() {
    return '/xhpast/';
  }

  public function getIcon() {
    return 'fa-ambulance';
  }

  public function getShortDescription() {
    return pht('Visual PHP Parser');
  }

  public function getTitleGlyph() {
    return "\xE2\x96\xA0";
  }

  public function getApplicationGroup() {
    return self::GROUP_DEVELOPER;
  }

  public function getRoutes() {
    return array(
      '/xhpast/' => array(
        '' => 'PhabricatorXHPASTViewRunController',
        'view/(?P<id>[1-9]\d*)/'
          => 'PhabricatorXHPASTViewFrameController',
        'frameset/(?P<id>[1-9]\d*)/'
          => 'PhabricatorXHPASTViewFramesetController',
        'input/(?P<id>[1-9]\d*)/'
          => 'PhabricatorXHPASTViewInputController',
        'tree/(?P<id>[1-9]\d*)/'
          => 'PhabricatorXHPASTViewTreeController',
        'stream/(?P<id>[1-9]\d*)/'
          => 'PhabricatorXHPASTViewStreamController',
      ),
    );
  }

}
