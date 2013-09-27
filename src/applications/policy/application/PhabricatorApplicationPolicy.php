<?php

final class PhabricatorApplicationPolicy extends PhabricatorApplication {

  public function shouldAppearInLaunchView() {
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
      ),
    );
  }

}

