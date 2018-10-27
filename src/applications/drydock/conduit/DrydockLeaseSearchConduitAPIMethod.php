<?php

final class DrydockLeaseSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'drydock.lease.search';
  }

  public function newSearchEngine() {
    return new DrydockLeaseSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Retrieve information about Drydock leases.');
  }

}
