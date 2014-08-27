<?php

abstract class PhortuneCartController
  extends PhortuneController {

  protected function buildCartContents(PhortuneCart $cart) {

    $rows = array();
    $total = 0;
    foreach ($cart->getPurchases() as $purchase) {
      $rows[] = array(
        $purchase->getFullDisplayName(),
        PhortuneCurrency::newFromUSDCents($purchase->getBasePriceInCents())
          ->formatForDisplay(),
        $purchase->getQuantity(),
        PhortuneCurrency::newFromUSDCents($purchase->getTotalPriceInCents())
          ->formatForDisplay(),
      );

      $total += $purchase->getTotalPriceInCents();
    }

    $rows[] = array(
      phutil_tag('strong', array(), pht('Total')),
      '',
      '',
      phutil_tag('strong', array(),
        PhortuneCurrency::newFromUSDCents($total)->formatForDisplay()),
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
      ->setHeaderText(pht('Cart Contents'))
      ->appendChild($table);
  }

}
