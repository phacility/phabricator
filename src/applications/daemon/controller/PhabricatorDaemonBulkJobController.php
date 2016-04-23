<?php

abstract class PhabricatorDaemonBulkJobController
  extends PhabricatorDaemonController {

  public function shouldRequireAdmin() {
    return false;
  }

  public function shouldAllowPublic() {
    return true;
  }

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new PhabricatorWorkerBulkJobSearchEngine());
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Bulk Jobs'), '/daemon/bulk/');
    return $crumbs;
  }

}
