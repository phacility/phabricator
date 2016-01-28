<?php

final class PHUIFeedStoryExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Feed Story');
  }

  public function getDescription() {
    return pht(
      'An outlandish exaggeration of intricate tales from around the realm');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    /* Basic Story */
    $text = hsprintf(
      '<strong><a>harding (Tom Harding)</a></strong> closed <a>'.
      'D12: New spacer classes for blog views</a>.');
    $story1 = id(new PHUIFeedStoryView())
      ->setTitle($text)
      ->setImage(celerity_get_resource_uri('/rsrc/image/people/harding.png'))
      ->setImageHref('http://en.wikipedia.org/wiki/Warren_G._Harding')
      ->setEpoch(1)
      ->setAppIcon('fa-star')
      ->setUser($user);

    /* Text Story, useful in Blogs, Ponders, Status */
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
    $text = hsprintf(
      '<strong><a>lincoln (Honest Abe)</a></strong> wrote a '.
      'new blog post.');
    $story2 = id(new PHUIFeedStoryView())
      ->setTitle($text)
      ->setImage(celerity_get_resource_uri('/rsrc/image/people/lincoln.png'))
      ->setImageHref('http://en.wikipedia.org/wiki/Abraham_Lincoln')
      ->setEpoch(strtotime('November 19, 1863'))
      ->setAppIcon('fa-star')
      ->setUser($user)
      ->setTokenBar($tokenview)
      ->setPontification(
        'Four score and seven years ago our fathers brought '.
        'forth on this continent, a new nation, conceived in Liberty, and '.
        'dedicated to the proposition that all men are created equal. '.
        'Now we are engaged in a great civil war, testing whether that '.
        'nation, or any nation so conceived and so dedicated, can long '.
        'endure. We are met on a great battle-field of that war. We have '.
        'come to dedicate a portion of that field, as a final resting '.
        'place for those who here gave their lives that that nation might '.
        'live. It is altogether fitting and proper that we should do this.',
        'Gettysburg Address');

    /* Action Story, let's give people tokens! */

    $text = hsprintf(
      '<strong><a>harding (Tom Harding)</a></strong> awarded '.
      '<a>M10: Workboards</a> a token.');
    $action1 = id(new PHUIIconView())
      ->setIcon('fa-trophy bluegrey')
      ->setHref('#');
    $token =
        id(new PHUIIconView())
          ->setSpriteSheet(PHUIIconView::SPRITE_TOKENS)
          ->setSpriteIcon('like-1');
    $story3 = id(new PHUIFeedStoryView())
      ->setTitle($text)
      ->setImage(celerity_get_resource_uri('/rsrc/image/people/harding.png'))
      ->setImageHref('http://en.wikipedia.org/wiki/Warren_G._Harding')
      ->appendChild($token)
      ->setEpoch(1)
      ->addAction($action1)
      ->setAppIcon('fa-trophy')
      ->setUser($user);

    /* Image Story, used in Pholio, Macro */
    $text = hsprintf(
      '<strong><a>wgharding (Warren Harding)</a></strong> '.
      'asked a new question.');
    $action1 = id(new PHUIIconView())
      ->setIcon('fa-chevron-up bluegrey')
      ->setHref('#');
    $action2 = id(new PHUIIconView())
      ->setIcon('fa-chevron-down bluegrey')
      ->setHref('#');
    $story4 = id(new PHUIFeedStoryView())
      ->setTitle($text)
      ->setImage(celerity_get_resource_uri('/rsrc/image/people/harding.png'))
      ->setImageHref('http://en.wikipedia.org/wiki/Warren_G._Harding')
      ->setEpoch(1)
      ->setAppIcon('fa-cogs')
      ->setPontification(
        'Why does inline-block add space under my spans and anchors?')
      ->addAction($action1)
      ->addAction($action2)
      ->setUser($user);

    /* Text Story, useful in Blogs, Ponders, Status */
    $text = hsprintf(
      '<strong><a>lincoln (Honest Abe)</a></strong> updated '.
      'his status.');
    $story5 = id(new PHUIFeedStoryView())
      ->setTitle($text)
      ->setImage(celerity_get_resource_uri('/rsrc/image/people/lincoln.png'))
      ->setImageHref('http://en.wikipedia.org/wiki/Abraham_Lincoln')
      ->setEpoch(strtotime('November 19, 1863'))
      ->setAppIcon('fa-rocket')
      ->setUser($user)
      ->setPontification(
        'If we ever create a lightweight status app '.
        'this story would be how that would be displayed.');

    /* Basic "One Line" Story */
    $text = hsprintf(
      '<strong><a>harding (Tom Harding)</a></strong> updated <a>'.
      'D12: New spacer classes for blog views</a>.');
    $story6 = id(new PHUIFeedStoryView())
      ->setTitle($text)
      ->setImage(celerity_get_resource_uri('/rsrc/image/people/harding.png'))
      ->setImageHref('http://en.wikipedia.org/wiki/Warren_G._Harding')
      ->setEpoch(1)
      ->setAppIcon('fa-wifi')
      ->setUser($user);


    $head1 = id(new PHUIHeaderView())
      ->setHeader(pht('Basic Story'));

    $head2 = id(new PHUIHeaderView())
      ->setHeader(pht('Title / Text Story'));

    $head3 = id(new PHUIHeaderView())
      ->setHeader(pht('Token Story'));

    $head4 = id(new PHUIHeaderView())
      ->setHeader(pht('Action Story'));

    $head5 = id(new PHUIHeaderView())
      ->setHeader(pht('Status Story'));

    $head6 = id(new PHUIHeaderView())
      ->setHeader(pht('One Line Story'));

    $wrap1 =
      array(
        id(new PHUIBoxView())
          ->appendChild($story1)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL),
      );

    $wrap2 =
      array(
        id(new PHUIBoxView())
          ->appendChild($story2)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL),
      );

    $wrap3 =
      array(
        id(new PHUIBoxView())
          ->appendChild($story3)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL),
      );

    $wrap4 =
      array(
        id(new PHUIBoxView())
          ->appendChild($story4)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL),
      );

    $wrap5 =
      array(
        id(new PHUIBoxView())
          ->appendChild($story5)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL),
      );

    $wrap6 =
      array(
        id(new PHUIBoxView())
          ->appendChild($story6)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL),
      );

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
          $wrap4,
          $head5,
          $wrap5,
          $head6,
          $wrap6,
        ));
  }
}
