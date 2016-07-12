<?php

final class ReleephQueryProductsConduitAPIMethod
  extends ReleephConduitAPIMethod {

  public function getAPIMethodName() {
    return 'releeph.queryproducts';
  }

  public function getMethodDescription() {
    return pht('Query information about Releeph products.');
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'optional list<id>',
      'phids' => 'optional list<phid>',
      'repositoryPHIDs' => 'optional list<phid>',
      'isActive' => 'optional bool',
    ) + $this->getPagerParamTypes();
  }

  protected function defineReturnType() {
    return 'query-results';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $query = id(new ReleephProductQuery())
      ->setViewer($viewer);

    $ids = $request->getValue('ids');
    if ($ids !== null) {
      $query->withIDs($ids);
    }

    $phids = $request->getValue('phids');
    if ($phids !== null) {
      $query->withPHIDs($phids);
    }

    $repository_phids = $request->getValue('repositoryPHIDs');
    if ($repository_phids !== null) {
      $query->withRepositoryPHIDs($repository_phids);
    }

    $is_active = $request->getValue('isActive');
    if ($is_active !== null) {
      $query->withActive($is_active);
    }

    $pager = $this->newPager($request);
    $products = $query->executeWithCursorPager($pager);

    $data = array();
    foreach ($products as $product) {
      $id = $product->getID();

      $uri = '/releeph/product/'.$id.'/';
      $uri = PhabricatorEnv::getProductionURI($uri);

      $data[] = array(
        'id' => $id,
        'phid' => $product->getPHID(),
        'uri' => $uri,
        'name' => $product->getName(),
        'isActive' => (bool)$product->getIsActive(),
        'repositoryPHID' => $product->getRepositoryPHID(),
      );
    }

    return $this->addPagerResults(
      array(
        'data' => $data,
      ),
      $pager);
  }

}
