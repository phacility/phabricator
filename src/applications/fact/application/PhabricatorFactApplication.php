<?php

final class PhabricatorFactApplication extends PhabricatorApplication {

  public function getShortDescription() {
    return pht('Chart and Analyze Data');
  }

  public function getName() {
    return pht('Facts');
  }

  public function getBaseURI() {
    return '/fact/';
  }

  public function getIcon() {
    return 'fa-line-chart';
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRoutes() {
    return array(
      '/fact/' => array(
        '' => 'PhabricatorFactHomeController',
        'chart/(?P<chartKey>[^/]+)/(?:(?P<mode>draw)/)?' =>
          'PhabricatorFactChartController',
        'object/(?<phid>[^/]+)/' => 'PhabricatorFactObjectController',
      ),
    );
  }

}
