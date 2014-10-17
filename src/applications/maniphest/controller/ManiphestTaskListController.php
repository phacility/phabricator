<?php

final class ManiphestTaskListController
  extends ManiphestController {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(
        id(new ManiphestTaskSearchEngine())
          ->setShowBatchControls(true))
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

}
