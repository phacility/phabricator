<?php

final class PhabricatorTypeaheadApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Typeahead');
  }

  public function getRoutes() {
    return array(
      '/typeahead/' => array(
        '(?P<action>browse|class)/(?:(?P<class>\w+)/)?'
          => 'PhabricatorTypeaheadModularDatasourceController',
        'help/(?P<class>\w+)/'
          => 'PhabricatorTypeaheadFunctionHelpController',
      ),
    );
  }

  public function isLaunchable() {
    return false;
  }

  public function canUninstall() {
    return false;
  }

}
