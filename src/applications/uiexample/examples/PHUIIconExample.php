<?php

final class PHUIIconExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Icons and Images');
  }

  public function getDescription() {
    return pht('Easily render icons or images with links and sprites.');
  }

  private function listTransforms() {
    return array(
      'ph-rotate-90',
      'ph-rotate-180',
      'ph-rotate-270',
      'ph-flip-horizontal',
      'ph-flip-vertical',
      'ph-spin',
    );
  }

  public function renderExample() {

    $colors = PHUIIconView::getFontIconColors();
    $colors = array_merge(array(null), $colors);
    $fas = PHUIIconView::getFontIcons();

    $trans = $this->listTransforms();

    $cicons = array();
    foreach ($colors as $color) {
      $cicons[] = id(new PHUIIconView())
        ->addClass('phui-example-icon-transform')
        ->setIconFont('fa-tag '.$color)
        ->setText(pht('fa-tag %s', $color));
    }
    $ficons = array();
    sort($fas);
    foreach ($fas as $fa) {
      $ficons[] = id(new PHUIIconView())
        ->addClass('phui-example-icon-name')
        ->setIconFont($fa)
        ->setText($fa);
    }

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

    $tokens = array(
      'like-1',
      'like-2',
      'heart-1',
      'heart-2',
    );
    $tokenview = array();
    foreach ($tokens as $token) {
      $tokenview[] =
        id(new PHUIIconView())
          ->setSpriteSheet(PHUIIconView::SPRITE_TOKENS)
          ->setSpriteIcon($token);
    }

    $logins = array(
      'Asana',
      'Dropbox',
      'Google',
      'Github',
    );
    $loginview = array();
    foreach ($logins as $login) {
      $loginview[] =
        id(new PHUIIconView())
          ->setSpriteSheet(PHUIIconView::SPRITE_LOGIN)
          ->setSpriteIcon($login)
          ->addClass(PHUI::MARGIN_SMALL_RIGHT);
    }

    $layout_cicons = id(new PHUIBoxView())
      ->appendChild($cicons)
      ->addMargin(PHUI::MARGIN_LARGE);

    $layout_fa = id(new PHUIBoxView())
      ->appendChild($ficons)
      ->addMargin(PHUI::MARGIN_LARGE);

    $layout2 = id(new PHUIBoxView())
      ->appendChild(array($person1, $person2, $person3))
      ->addMargin(PHUI::MARGIN_MEDIUM);

    $layout2a = id(new PHUIBoxView())
      ->appendChild(array($person4, $person5, $person6))
      ->addMargin(PHUI::MARGIN_MEDIUM);

    $layout3 = id(new PHUIBoxView())
      ->appendChild($tokenview)
      ->addMargin(PHUI::MARGIN_MEDIUM);

    $layout5 = id(new PHUIBoxView())
      ->appendChild($loginview)
      ->addMargin(PHUI::MARGIN_MEDIUM);

    $fa_link = phutil_tag(
      'a',
      array(
        'href' => 'http://fontawesome.io',
      ),
      'http://fontawesome.io');
    $fa_text = pht('Font Awesome by Dave Gandy - %s', $fa_link);

    $fontawesome = id(new PHUIObjectBoxView())
      ->setHeaderText($fa_text)
      ->appendChild($layout_fa);

    $transforms = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Colors and Transforms'))
      ->appendChild($layout_cicons);

    $wrap2 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('People!'))
      ->appendChild(array($layout2, $layout2a));

    $wrap3 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Tokens'))
      ->appendChild($layout3);

    $wrap5 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Authentication'))
      ->appendChild($layout5);

    return phutil_tag(
      'div',
        array(
          'class' => 'phui-icon-example',
        ),
        array(
          $fontawesome,
          $transforms,
          $wrap2,
          $wrap3,
          $wrap5,
        ));
  }
}
