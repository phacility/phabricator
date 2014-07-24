<?php

final class PhabricatorPolicyApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Policy');
  }

  public function isLaunchable() {
    return false;
  }

  public function canUninstall() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/policy/' => array(
        'explain/(?P<phid>[^/]+)/(?P<capability>[^/]+)/'
          => 'PhabricatorPolicyExplainController',
        'edit/(?:(?P<phid>[^/]+)/)?' => 'PhabricatorPolicyEditController',
      ),
    );
  }

}
