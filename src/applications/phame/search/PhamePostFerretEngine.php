<?php

final class PhamePostFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'phame';
  }

  public function getScopeName() {
    return 'post';
  }

  public function newSearchEngine() {
    return new PhamePostSearchEngine();
  }

}
