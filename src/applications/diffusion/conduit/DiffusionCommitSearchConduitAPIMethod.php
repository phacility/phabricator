<?php

final class DiffusionCommitSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.commit.search';
  }

  public function newSearchEngine() {
    return new PhabricatorCommitSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about commits.');
  }

}
