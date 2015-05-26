<?php

final class PhabricatorMultimeterApplication
  extends PhabricatorApplication {

  public function getName() {
    return pht('Multimeter');
  }

  public function getBaseURI() {
    return '/multimeter/';
  }

  public function getFontIcon() {
    return 'fa-motorcycle';
  }

  public function isPrototype() {
    return true;
  }

  public function getTitleGlyph() {
    return "\xE2\x8F\xB3";
  }

  public function getApplicationGroup() {
    return self::GROUP_DEVELOPER;
  }

  public function getShortDescription() {
    return pht('Performance Sampler');
  }

  public function getRemarkupRules() {
    return array();
  }

  public function getRoutes() {
    return array(
      '/multimeter/' => array(
        '' => 'MultimeterSampleController',
      ),
    );
  }

}
