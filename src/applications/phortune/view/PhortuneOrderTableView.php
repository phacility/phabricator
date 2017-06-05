<?php

final class PhortuneOrderTableView extends AphrontView {

  private $carts;
  private $handles;
  private $noDataString;
  private $isInvoices;
  private $isMerchantView;

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function getHandles() {
    return $this->handles;
  }

  public function setCarts(array $carts) {
    $this->carts = $carts;
    return $this;
  }

  public function getCarts() {
    return $this->carts;
  }

  public function setIsInvoices($is_invoices) {
    $this->isInvoices = $is_invoices;
    return $this;
  }

  public function getIsInvoices() {
    return $this->isInvoices;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function getNoDataString() {
    return $this->noDataString;
  }

  public function setIsMerchantView($is_merchant_view) {
    $this->isMerchantView = $is_merchant_view;
    return $this;
  }

  public function getIsMerchantView() {
    return $this->isMerchantView;
  }

  public function render() {
    $carts = $this->getCarts();
    $handles = $this->getHandles();
    $viewer = $this->getUser();

    $is_invoices = $this->getIsInvoices();
    $is_merchant = $this->getIsMerchantView();

    $rows = array();
    $rowc = array();
    foreach ($carts as $cart) {
      $cart_link = $handles[$cart->getPHID()]->renderLink();
      $purchases = $cart->getPurchases();

      if (count($purchases) == 1) {
        $purchase = head($purchases);
        $purchase_name = $handles[$purchase->getPHID()]->renderLink();
        $purchases = array();
      } else {
        $purchase_name = '';
      }

      if ($is_invoices) {
        $merchant_link = $handles[$cart->getMerchantPHID()]->renderLink();
      } else {
        $merchant_link = null;
      }

      $rowc[] = '';
      $rows[] = array(
        $cart->getID(),
        $merchant_link,
        phutil_tag(
          'strong',
          array(),
          $cart_link),
        $purchase_name,
        phutil_tag(
          'strong',
          array(),
          $cart->getTotalPriceAsCurrency()->formatForDisplay()),
        PhortuneCart::getNameForStatus($cart->getStatus()),
        phabricator_datetime($cart->getDateModified(), $viewer),
        phabricator_datetime($cart->getDateCreated(), $viewer),
        phutil_tag(
          'a',
          array(
            'href' => $cart->getCheckoutURI(),
            'class' => 'small button button-green',
          ),
          pht('Pay Now')),
      );
      foreach ($purchases as $purchase) {
        $id = $purchase->getID();

        $price = $purchase->getTotalPriceAsCurrency()->formatForDisplay();

        $rowc[] = '';
        $rows[] = array(
          '',
          '',
          $handles[$purchase->getPHID()]->renderLink(),
          $price,
          '',
          '',
          '',
          '',
        );
      }
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString($this->getNoDataString())
      ->setRowClasses($rowc)
      ->setHeaders(
        array(
          pht('ID'),
          pht('Merchant'),
          $is_invoices ? pht('Invoice') : pht('Order'),
          pht('Purchase'),
          pht('Amount'),
          pht('Status'),
          pht('Updated'),
          pht('Invoice Date'),
          null,
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          '',
          'wide',
          'right',
          '',
          'right',
          'right',
          'action',
        ))
      ->setColumnVisibility(
        array(
          true,
          $is_invoices,
          true,
          true,
          true,
          !$is_invoices,
          !$is_invoices,
          $is_invoices,

          // We show "Pay Now" for due invoices, but not if the viewer is the
          // merchant, since it doesn't make sense for them to pay.
          ($is_invoices && !$is_merchant),
        ));

    return $table;
  }

}
