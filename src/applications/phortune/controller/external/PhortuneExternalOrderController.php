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

    $is_printable = ($request->getURIData('action') === 'print');

    $order_view = id(new PhortuneOrderSummaryView())
      ->setViewer($xviewer)
      ->setOrder($order)
      ->setPrintable($is_printable);

    $crumbs = null;
    $curtain = null;

    $main = array();
    $tail = array();

    require_celerity_resource('phortune-invoice-css');

    if ($is_printable) {
      $body_class = 'phortune-invoice-view';

      $tail[] = $order_view;
    } else {
      $body_class = 'phortune-cart-page';

      $curtain = $this->newCurtain($order);

      $crumbs = $this->newExternalCrumbs()
        ->addTextCrumb($order->getObjectName())
        ->setBorder(true);

      $timeline = $this->buildTransactionTimeline($order)
        ->setShouldTerminate(true);

      $main[] = $order_view;
      $main[] = $timeline;
    }

    $column_view = id(new PHUITwoColumnView())
      ->setMainColumn($main)
      ->setFooter($tail);

    if ($curtain) {
      $column_view->setCurtain($curtain);
    }

    $page = $this->newPage()
      ->addClass($body_class)
      ->setTitle(
        array(
          $order->getObjectName(),
          $order->getName(),
        ))
      ->appendChild($column_view);

    if ($crumbs) {
      $page->setCrumbs($crumbs);
    }

    return $page;
  }


  private function newCurtain(PhortuneCart $order) {
    $xviewer = $this->getExternalViewer();
    $email = $this->getAccountEmail();

    $curtain = $this->newCurtainView($order);

    $print_uri = $email->getExternalOrderPrintURI($order);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Printable Version'))
        ->setHref($print_uri)
        ->setOpenInNewWindow(true)
        ->setIcon('fa-print'));

    return $curtain;
  }

}
