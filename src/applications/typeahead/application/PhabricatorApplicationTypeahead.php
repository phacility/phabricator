<?php

final class PhabricatorApplicationTypeahead extends PhabricatorApplication {

  public function getRoutes() {
    return array(
      '/typeahead/' => array(
        'common/(?P<type>\w+)/'
          => 'PhabricatorTypeaheadCommonDatasourceController',
        'class/(?:(?P<class>\w+)/)?'
          => 'PhabricatorTypeaheadModularDatasourceController',
      ),
    );
  }

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function canUninstall() {
    return false;
  }

}
