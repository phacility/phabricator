<?php

final class ReleephProductHistoryController extends ReleephProductController {

  private $id;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['projectID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $product = id(new ReleephProductQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$product) {
      return new Aphront404Response();
    }
    $this->setProduct($product);

    $timeline = $this->buildTransactionTimeline(
      $product,
      new ReleephProductTransactionQuery());
    $timeline->setShouldTerminate(true);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('History'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $timeline,
      ),
      array(
        'title' => pht('Product History'),
      ));
  }

}
