<?php

final class PhortuneOrderTableView extends AphrontView {

  private $carts;
  private $handles;

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

  public function render() {
    $carts = $this->getCarts();
    $handles = $this->getHandles();
    $viewer = $this->getUser();

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

      $rowc[] = '';
      $rows[] = array(
        $cart->getID(),
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
      );
      foreach ($purchases as $purchase) {
        $id = $purchase->getID();

        $price = $purchase->getTotalPriceAsCurrency()->formatForDisplay();

        $rowc[] = '';
        $rows[] = array(
          '',
          $handles[$purchase->getPHID()]->renderLink(),
          $price,
          '',
          '',
        );
      }
    }

    $table = id(new AphrontTableView($rows))
      ->setRowClasses($rowc)
      ->setHeaders(
        array(
          pht('ID'),
          pht('Order'),
          pht('Purchase'),
          pht('Amount'),
          pht('Status'),
          pht('Updated'),
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          'wide',
          'right',
          '',
          'right',
        ));

    return $table;
  }

}
