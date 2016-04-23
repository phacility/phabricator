<?php

final class DiffusionRepositorySearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.repository.search';
  }

  public function newSearchEngine() {
    return new PhabricatorRepositorySearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about repositories.');
  }

}
