<?php

final class PhabricatorApplicationFact extends PhabricatorApplication {

  public function getShortDescription() {
    return 'Analyze Data';
  }

  public function getBaseURI() {
    return '/fact/';
  }

  public function getAutospriteName() {
    return 'fact';
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/fact/' => array(
        '' => 'PhabricatorFactHomeController',
        'chart/' => 'PhabricatorFactChartController',
      ),
    );
  }

}
