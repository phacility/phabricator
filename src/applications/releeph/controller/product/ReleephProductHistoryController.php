<?php

final class ReleephProductHistoryController extends ReleephProductController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('projectID');

    $product = id(new ReleephProductQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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
    $crumbs->setBorder(true);

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
