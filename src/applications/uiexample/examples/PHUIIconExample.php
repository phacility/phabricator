<?php

final class PHUIIconExample extends PhabricatorUIExample {

  public function getName() {
    return 'Icons and Images';
  }

  public function getDescription() {
    return 'Easily render icons or images with links and sprites.';
  }

  public function renderExample() {

    $person1 = new PHUIIconView();
    $person1->setHeadSize(PHUIIconView::HEAD_MEDIUM);
    $person1->setHref('http://en.wikipedia.org/wiki/George_Washington');
    $person1->setImage(
      celerity_get_resource_uri('/rsrc/image/people/washington.png'));

    $person2 = new PHUIIconView();
    $person2->setHeadSize(PHUIIconView::HEAD_MEDIUM);
    $person2->setHref('http://en.wikipedia.org/wiki/Warren_G._Harding');
    $person2->setImage(
      celerity_get_resource_uri('/rsrc/image/people/harding.png'));

    $person3 = new PHUIIconView();
    $person3->setHeadSize(PHUIIconView::HEAD_MEDIUM);
    $person3->setHref('http://en.wikipedia.org/wiki/William_Howard_Taft');
    $person3->setImage(
      celerity_get_resource_uri('/rsrc/image/people/taft.png'));

    $person4 = new PHUIIconView();
    $person4->setHeadSize(PHUIIconView::HEAD_SMALL);
    $person4->setHref('http://en.wikipedia.org/wiki/George_Washington');
    $person4->setImage(
      celerity_get_resource_uri('/rsrc/image/people/washington.png'));

    $person5 = new PHUIIconView();
    $person5->setHeadSize(PHUIIconView::HEAD_SMALL);
    $person5->setHref('http://en.wikipedia.org/wiki/Warren_G._Harding');
    $person5->setImage(
      celerity_get_resource_uri('/rsrc/image/people/harding.png'));

    $person6 = new PHUIIconView();
    $person6->setHeadSize(PHUIIconView::HEAD_SMALL);
    $person6->setHref('http://en.wikipedia.org/wiki/William_Howard_Taft');
    $person6->setImage(
      celerity_get_resource_uri('/rsrc/image/people/taft.png'));

    $card1 = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_PAYMENTS)
      ->setSpriteIcon('visa')
      ->addClass(PHUI::MARGIN_SMALL_RIGHT);

    $card2 = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_PAYMENTS)
      ->setSpriteIcon('mastercard')
      ->addClass(PHUI::MARGIN_SMALL_RIGHT);

    $card3 = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_PAYMENTS)
      ->setSpriteIcon('paypal')
      ->addClass(PHUI::MARGIN_SMALL_RIGHT);

    $card4 = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_PAYMENTS)
      ->setSpriteIcon('americanexpress')
      ->addClass(PHUI::MARGIN_SMALL_RIGHT);

    $card5 = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_PAYMENTS)
      ->setSpriteIcon('googlecheckout');

    $actions = array(
      'settings-grey',
      'heart-grey',
      'tag-grey',
      'new-grey',
      'search-grey',
      'move-grey');
    $actionview = array();
    foreach ($actions as $action) {
      $actionview[] = id(new PHUIIconView())
        ->setSpriteSheet(PHUIIconView::SPRITE_ACTIONS)
        ->setSpriteIcon($action)
        ->setHref('#');
    }

    $tokens = array(
      'like-1',
      'like-2',
      'heart-1',
      'heart-2');
    $tokenview = array();
    foreach ($tokens as $token) {
      $tokenview[] =
        id(new PHUIIconView())
          ->setSpriteSheet(PHUIIconView::SPRITE_TOKENS)
          ->setSpriteIcon($token);
    }

    $layout1 =
      array(
        id(new PHUIBoxView())
          ->appendChild($actionview)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL)
          ->setShadow(true));

    $layout2 =
      array(
        id(new PHUIBoxView())
          ->appendChild(array($person1, $person2, $person3))
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL)
          ->setShadow(true));

    $layout2a =
      array(
        id(new PHUIBoxView())
          ->appendChild(array($person4, $person5, $person6))
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL)
          ->setShadow(true));

    $layout3 =
      array(
        id(new PHUIBoxView())
          ->appendChild($tokenview)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL)
          ->setShadow(true));

    $layout4 =
      array(
        id(new PHUIBoxView())
          ->appendChild(array($card1, $card2, $card3, $card4, $card5))
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_MEDIUM)
          ->setShadow(true));

    $head1 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Action Icons!'));

    $head2 = id(new PhabricatorHeaderView())
      ->setHeader(pht('People!'));

    $head3 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Tokens'));

    $head4 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Payments'));

    $wrap1 = id(new PHUIBoxView())
      ->appendChild($layout1)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap2 = id(new PHUIBoxView())
      ->appendChild(array($layout2, $layout2a))
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap3 = id(new PHUIBoxView())
      ->appendChild($layout3)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap4 = id(new PHUIBoxView())
      ->appendChild($layout4)
      ->addMargin(PHUI::MARGIN_LARGE);

    return phutil_tag(
      'div',
        array(),
        array(
          $head1,
          $wrap1,
          $head2,
          $wrap2,
          $head3,
          $wrap3,
          $head4,
          $wrap4
        ));
        }
}
