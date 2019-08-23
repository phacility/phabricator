<?php

final class PhortuneExternalOverviewController
  extends PhortuneExternalController {

  protected function handleExternalRequest(AphrontRequest $request) {
    $xviewer = $this->getExternalViewer();
    $email = $this->getAccountEmail();
    $account = $email->getAccount();

    $crumbs = $this->newExternalCrumbs()
      ->addTextCrumb(pht('Viewing As "%s"', $email->getAddress()))
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Invoices and Receipts: %s', $account->getName()))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon('fa-times')
          ->setText(pht('Unsubscribe'))
          ->setHref($email->getUnsubscribeURI())
          ->setWorkflow(true));

    $external_view = $this->newExternalView();
    $invoices_view = $this->newInvoicesView();
    $receipts_view = $this->newReceiptsView();

    $column_view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $external_view,
          $invoices_view,
          $receipts_view,
        ));

    return $this->newPage()
      ->setCrumbs($crumbs)
      ->setTitle(
        array(
          pht('Invoices and Receipts'),
          $account->getName(),
        ))
      ->appendChild($column_view);
  }

  private function newInvoicesView() {
    $xviewer = $this->getExternalViewer();
    $email = $this->getAccountEmail();
    $account = $email->getAccount();

    $invoices = id(new PhortuneCartQuery())
      ->setViewer($xviewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->needPurchases(true)
      ->withInvoices(true)
      ->execute();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Invoices'));

    $invoices_table = id(new PhortuneOrderTableView())
      ->setViewer($xviewer)
      ->setAccountEmail($email)
      ->setCarts($invoices)
      ->setIsInvoices(true);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($invoices_table);
  }

  private function newReceiptsView() {
    $xviewer = $this->getExternalViewer();
    $email = $this->getAccountEmail();
    $account = $email->getAccount();

    $receipts = id(new PhortuneCartQuery())
      ->setViewer($xviewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->needPurchases(true)
      ->withInvoices(false)
      ->execute();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Receipts'));

    $receipts_table = id(new PhortuneOrderTableView())
      ->setViewer($xviewer)
      ->setAccountEmail($email)
      ->setCarts($receipts);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($receipts_table);
  }

}
