<?php

final class PhabricatorDaemonBulkJobListController
  extends PhabricatorDaemonBulkJobController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhabricatorWorkerBulkJobSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
