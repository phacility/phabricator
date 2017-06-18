<?php

final class PHUIButtonBarExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Button Bar');
  }

  public function getDescription() {
    return pht('A minimal UI for Buttons');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // Icon Buttons
    $icons = array(
      'Go Back' => 'fa-chevron-left bluegrey',
      'Choose Date' => 'fa-calendar bluegrey',
      'Edit View' => 'fa-pencil bluegrey',
      'Go Forward' => 'fa-chevron-right bluegrey',
    );
    $button_bar1 = new PHUIButtonBarView();
    foreach ($icons as $text => $icon) {
      $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::GREY)
        ->setTitle($text)
        ->setIcon($icon);

      $button_bar1->addButton($button);
    }

    $button_bar2 = new PHUIButtonBarView();
    foreach ($icons as $text => $icon) {
      $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE)
        ->setTitle($text)
        ->setText($text);

      $button_bar2->addButton($button);
    }

    $button_bar3 = new PHUIButtonBarView();
    foreach ($icons as $text => $icon) {
      $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE)
        ->setTitle($text)
        ->setTooltip($text)
        ->setIcon($icon);

      $button_bar3->addButton($button);
    }

    $button_bar4 = new PHUIButtonBarView();
    $button_bar4->setBorderless(true);
    foreach ($icons as $text => $icon) {
      $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setTitle($text)
        ->setTooltip($text)
        ->setIcon($icon);

      $button_bar4->addButton($button);
    }

    $layout1 = id(new PHUIBoxView())
      ->appendChild($button_bar1)
      ->addClass('ml');

    $layout2 = id(new PHUIBoxView())
      ->appendChild($button_bar2)
      ->addClass('mlr mll mlb');

    $layout3 = id(new PHUIBoxView())
      ->appendChild($button_bar3)
      ->addClass('mlr mll mlb');

    $layout4 = id(new PHUIBoxView())
      ->appendChild($button_bar4)
      ->addClass('mlr mll mlb');

    $wrap1 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Button Bar Example'))
      ->appendChild($layout1)
      ->appendChild($layout2)
      ->appendChild($layout3)
      ->appendChild($layout4);

    return array($wrap1);
  }
}
