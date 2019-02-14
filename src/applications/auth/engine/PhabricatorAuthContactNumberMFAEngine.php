<?php

final class PhabricatorAuthContactNumberMFAEngine
  extends PhabricatorEditEngineMFAEngine {

  public function shouldTryMFA() {
    return true;
  }

}
