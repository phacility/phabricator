<?php

abstract class PhabricatorSite extends AphrontSite {

  public function shouldRequireHTTPS() {
    return PhabricatorEnv::getEnvConfig('security.require-https');
  }

}
