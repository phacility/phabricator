<?php

final class PhamePostSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'phame.post.search';
  }

  public function newSearchEngine() {
    return new PhamePostSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about blog posts.');
  }

}
