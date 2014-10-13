<?php

final class PhortuneChargeTableView extends AphrontView {

  private $charges;
  private $handles;
  private $showOrder;

  public function setShowOrder($show_order) {
    $this->showOrder = $show_order;
    return $this;
  }

  public function getShowOrder() {
    return $this->showOrder;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function getHandles() {
    return $this->handles;
  }

  public function setCharges(array $charges) {
    $this->charges = $charges;
    return $this;
  }

  public function getCharges() {
    return $this->charges;
  }

  public function render() {
    $charges = $this->getCharges();
    $handles = $this->getHandles();
    $viewer = $this->getUser();

    $rows = array();
    foreach ($charges as $charge) {
      $rows[] = array(
        $charge->getID(),
        $handles[$charge->getCartPHID()]->renderLink(),
        $handles[$charge->getProviderPHID()]->renderLink(),
        $charge->getPaymentMethodPHID()
          ? $handles[$charge->getPaymentMethodPHID()]->renderLink()
          : null,
        $handles[$charge->getMerchantPHID()]->renderLink(),
        $charge->getAmountAsCurrency()->formatForDisplay(),
        $charge->getStatusForDisplay(),
        phabricator_datetime($charge->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Cart'),
          pht('Provider'),
          pht('Method'),
          pht('Merchant'),
          pht('Amount'),
          pht('Status'),
          pht('Created'),
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          '',
          '',
          '',
          'wide right',
          '',
          '',
        ))
      ->setColumnVisibility(
        array(
          true,
          $this->getShowOrder(),
        ));

    return $table;
  }

}
