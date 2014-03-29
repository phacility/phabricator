<?php

final class ReleephProductHistoryController extends ReleephProductController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['projectID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $product = id(new ReleephProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$product) {
      return new Aphront404Response();
    }
    $this->setProduct($product);

    $xactions = id(new ReleephProductTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($product->getPHID()))
      ->execute();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($product->getPHID())
      ->setTransactions($xactions)
      ->setShouldTerminate(true);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('History'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $timeline,
      ),
      array(
        'title' => pht('Product History'),
        'device' => true,
      ));
  }

}
