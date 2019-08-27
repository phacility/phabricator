<?php

final class PhortuneOrderDescriptionView
  extends AphrontView {

  private $order;

  public function setOrder(PhortuneCart $order) {
    $this->order = $order;
    return $this;
  }

  public function getOrder() {
    return $this->order;
  }

  public function render() {
    $viewer = $this->getViewer();
    $order = $this->getOrder();

    $description = $order->getDescription();
    if (!strlen($description)) {
      return null;
    }

    $output = new PHUIRemarkupView($viewer, $description);

    $description_box = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->appendChild($output);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Description'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($description_box);
  }


}
