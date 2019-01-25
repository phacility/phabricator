<?php

final class PhabricatorAuthFactorProviderMFAEngine
  extends PhabricatorEditEngineMFAEngine {

  public function shouldTryMFA() {
    return true;
  }

}
