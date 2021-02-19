<?php

final class DifferentialChangesetSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'differential.changeset.search';
  }

  public function newSearchEngine() {
    return new DifferentialChangesetSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about changesets.');
  }

}
