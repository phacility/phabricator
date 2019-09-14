<?php

final class PhortuneOrderItemsView
  extends PhortuneOrderView {

  public function render() {
    $viewer = $this->getViewer();
    $order = $this->getOrder();

    $purchases = id(new PhortunePurchaseQuery())
      ->setViewer($viewer)
      ->withCartPHIDs(array($order->getPHID()))
      ->execute();

    $order->attachPurchases($purchases);

    $rows = array();
    foreach ($purchases as $purchase) {
      $rows[] = array(
        $purchase->getFullDisplayName(),
        $purchase->getBasePriceAsCurrency()->formatForDisplay(),
        $purchase->getQuantity(),
        $purchase->getTotalPriceAsCurrency()->formatForDisplay(),
      );
    }

    $rows[] = array(
      phutil_tag('strong', array(), pht('Total')),
      '',
      '',
      phutil_tag('strong', array(),
        $order->getTotalPriceAsCurrency()->formatForDisplay()),
    );

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Item'),
        pht('Price'),
        pht('Qty.'),
        pht('Total'),
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'right',
        'right',
        'right',
      ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Items'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }


}
