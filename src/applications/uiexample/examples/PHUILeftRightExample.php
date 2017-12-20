<?php

final class PHUILeftRightExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Responsive left-right table');
  }

  public function getDescription() {
    return pht('Allows easily alignment of left/right UI elements.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $text = pht('This is a sample of some text.');
    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Actions'))
      ->setIcon('fa-bars');

    $content1 = id(new PHUILeftRightView())
      ->setLeft($text)
      ->setRight($button)
      ->setVerticalAlign(PHUILeftRightView::ALIGN_TOP);

    $content2 = id(new PHUILeftRightView())
      ->setLeft($text)
      ->setRight($button)
      ->setVerticalAlign(PHUILeftRightView::ALIGN_MIDDLE);

    $content3 = id(new PHUILeftRightView())
      ->setLeft($text)
      ->setRight($button)
      ->setVerticalAlign(PHUILeftRightView::ALIGN_BOTTOM);


    $head2 = id(new PHUIHeaderView())
      ->setHeader('Align Top')
      ->addClass('ml');

    $head3 = id(new PHUIHeaderView())
      ->setHeader(pht('Align Middle'))
      ->addClass('ml');

    $head4 = id(new PHUIHeaderView())
      ->setHeader(pht('Align Bottom'))
      ->addClass('ml');

    $wrap2 = id(new PHUIBoxView())
      ->appendChild($content1)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap3 = id(new PHUIBoxView())
      ->appendChild($content2)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap4 = id(new PHUIBoxView())
      ->appendChild($content3)
      ->addMargin(PHUI::MARGIN_LARGE);

    return array(
      $head2,
      $wrap2,
      $head3,
      $wrap3,
      $head4,
      $wrap4,
    );
  }
}
