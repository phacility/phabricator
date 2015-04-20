<?php

abstract class PhortuneCartController
  extends PhortuneController {

  protected function buildCartContentTable(PhortuneCart $cart) {

    $rows = array();
    foreach ($cart->getPurchases() as $purchase) {
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
        $cart->getTotalPriceAsCurrency()->formatForDisplay()),
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

    return $table;
  }

  protected function renderCartDescription(PhortuneCart $cart) {
    $description = $cart->getDescription();
    if (!strlen($description)) {
      return null;
    }

    $output = PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())
        ->setPreserveLinebreaks(true)
        ->setContent($description),
      'default',
      $this->getViewer());

    $box = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->appendChild($output);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Description'))
      ->appendChild($box);
  }

}
