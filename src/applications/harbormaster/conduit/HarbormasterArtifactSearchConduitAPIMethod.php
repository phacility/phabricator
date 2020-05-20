<?php

final class HarbormasterArtifactSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.artifact.search';
  }

  public function newSearchEngine() {
    return new HarbormasterArtifactSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Query information about build artifacts.');
  }

}
