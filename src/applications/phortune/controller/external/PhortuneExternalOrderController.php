<?php

final class PhortuneExternalOrderController
  extends PhortuneExternalController {

  protected function handleExternalRequest(AphrontRequest $request) {
    $xviewer = $this->getExternalViewer();
    $email = $this->getAccountEmail();
    $account = $email->getAccount();

    $order = id(new PhortuneCartQuery())
      ->setViewer($xviewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withIDs(array($request->getURIData('orderID')))
      ->executeOne();
    if (!$order) {
      return new Aphront404Response();
    }

    $timeline = $this->buildTransactionTimeline(
      $order,
      new PhortuneCartTransactionQuery());
    $timeline->setShouldTerminate(true);

    $crumbs = $this->newExternalCrumbs()
      ->addTextCrumb($order->getObjectName());

    $view = id(new PHUITwoColumnView())
      ->setMainColumn(
        array(
          $timeline,
        ));

    return $this->newPage()
      ->setTitle(pht('Order %d', $order->getID()))
      ->setCrumbs($crumbs)
      ->appendChild($view);
 }

}
