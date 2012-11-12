<?php

final class PhabricatorApplicationUIExamples extends PhabricatorApplication {

  public function getBaseURI() {
    return '/uiexample/';
  }

  public function getShortDescription() {
    return 'Developer UI Examples';
  }

  public function getAutospriteName() {
    return 'uiexample';
  }

  public function getTitleGlyph() {
    return "\xE2\x8F\x9A";
  }

  public function getFlavorText() {
    return pht('A gallery of modern art.');
  }

  public function getApplicationGroup() {
    return self::GROUP_DEVELOPER;
  }

  public function getApplicationOrder() {
    return 0.110;
  }

  public function getRoutes() {
    return array(
      '/uiexample/' => array(
        '' => 'PhabricatorUIExampleRenderController',
        'view/(?P<class>[^/]+)/' => 'PhabricatorUIExampleRenderController',
      ),
    );
  }

}
