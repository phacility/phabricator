<?php

final class PHUIIconExample extends PhabricatorUIExample {

  public function getName() {
    return 'Icons and Images';
  }

  public function getDescription() {
    return 'Easily render icons or images with links and sprites.';
  }

  private function listFontAwesome() {
    return array(
      'fa-glass',
      'fa-music',
      'fa-search',
      'fa-envelope-o',
      'fa-heart',
      'fa-star',
      'fa-star-o',
      'fa-user',
      'fa-film',
      'fa-th-large',
      'fa-th',
      'fa-th-list',
      'fa-check',
      'fa-times',
      'fa-search-plus',
      'fa-search-minus',
      'fa-power-off',
      'fa-signal',
      'fa-cog',
      'fa-trash-o',
      'fa-home',
      'fa-file-o',
      'fa-clock-o',
      'fa-road',
      'fa-download',
      'fa-arrow-circle-o-down',
      'fa-arrow-circle-o-up',
      'fa-inbox',
      'fa-play-circle-o',
      'fa-repeat',
      'fa-refresh',
      'fa-list-alt',
      'fa-lock',
      'fa-flag',
      'fa-headphones',
      'fa-volume-off',
      'fa-volume-down',
      'fa-volume-up',
      'fa-qrcode',
      'fa-barcode',
      'fa-tag',
      'fa-tags',
      'fa-book',
      'fa-bookmark',
      'fa-print',
      'fa-camera',
      'fa-font',
      'fa-bold',
      'fa-italic',
      'fa-text-height',
      'fa-text-width',
      'fa-align-left',
      'fa-align-center',
      'fa-align-right',
      'fa-align-justify',
      'fa-list',
      'fa-outdent',
      'fa-indent',
      'fa-video-camera',
      'fa-picture-o',
      'fa-pencil',
      'fa-map-marker',
      'fa-adjust',
      'fa-tint',
      'fa-pencil-square-o',
      'fa-share-square-o',
      'fa-check-square-o',
      'fa-arrows',
      'fa-step-backward',
      'fa-fast-backward',
      'fa-backward',
      'fa-play',
      'fa-pause',
      'fa-stop',
      'fa-forward',
      'fa-fast-forward',
      'fa-step-forward',
      'fa-eject',
      'fa-chevron-left',
      'fa-chevron-right',
      'fa-plus-circle',
      'fa-minus-circle',
      'fa-times-circle',
      'fa-check-circle',
      'fa-question-circle',
      'fa-info-circle',
      'fa-crosshairs',
      'fa-times-circle-o',
      'fa-check-circle-o',
      'fa-ban',
      'fa-arrow-left',
      'fa-arrow-right',
      'fa-arrow-up',
      'fa-arrow-down',
      'fa-share',
      'fa-expand',
      'fa-compress',
      'fa-plus',
      'fa-minus',
      'fa-asterisk',
      'fa-exclamation-circle',
      'fa-gift',
      'fa-leaf',
      'fa-fire',
      'fa-eye',
      'fa-eye-slash',
      'fa-exclamation-triangle',
      'fa-plane',
      'fa-calendar',
      'fa-random',
      'fa-comment',
      'fa-magnet',
      'fa-chevron-up',
      'fa-chevron-down',
      'fa-retweet',
      'fa-shopping-cart',
      'fa-folder',
      'fa-folder-open',
      'fa-arrows-v',
      'fa-arrows-h',
      'fa-bar-chart-o',
      'fa-twitter-square',
      'fa-facebook-square',
      'fa-camera-retro',
      'fa-key',
      'fa-cogs',
      'fa-comments',
      'fa-thumbs-o-up',
      'fa-thumbs-o-down',
      'fa-star-half',
      'fa-heart-o',
      'fa-sign-out',
      'fa-linkedin-square',
      'fa-thumb-tack',
      'fa-external-link',
      'fa-sign-in',
      'fa-trophy',
      'fa-github-square',
      'fa-upload',
      'fa-lemon-o',
      'fa-phone',
      'fa-square-o',
      'fa-bookmark-o',
      'fa-phone-square',
      'fa-twitter',
      'fa-facebook',
      'fa-github',
      'fa-unlock',
      'fa-credit-card',
      'fa-rss',
      'fa-hdd-o',
      'fa-bullhorn',
      'fa-bell',
      'fa-certificate',
      'fa-hand-o-right',
      'fa-hand-o-left',
      'fa-hand-o-up',
      'fa-hand-o-down',
      'fa-arrow-circle-left',
      'fa-arrow-circle-right',
      'fa-arrow-circle-up',
      'fa-arrow-circle-down',
      'fa-globe',
      'fa-wrench',
      'fa-tasks',
      'fa-filter',
      'fa-briefcase',
      'fa-arrows-alt',
      'fa-users',
      'fa-link',
      'fa-cloud',
      'fa-flask',
      'fa-scissors',
      'fa-files-o',
      'fa-paperclip',
      'fa-floppy-o',
      'fa-square',
      'fa-bars',
      'fa-list-ul',
      'fa-list-ol',
      'fa-strikethrough',
      'fa-underline',
      'fa-table',
      'fa-magic',
      'fa-truck',
      'fa-pinterest',
      'fa-pinterest-square',
      'fa-google-plus-square',
      'fa-google-plus',
      'fa-money',
      'fa-caret-down',
      'fa-caret-up',
      'fa-caret-left',
      'fa-caret-right',
      'fa-columns',
      'fa-sort',
      'fa-sort-asc',
      'fa-sort-desc',
      'fa-envelope',
      'fa-linkedin',
      'fa-undo',
      'fa-gavel',
      'fa-tachometer',
      'fa-comment-o',
      'fa-comments-o',
      'fa-bolt',
      'fa-sitemap',
      'fa-umbrella',
      'fa-clipboard',
      'fa-lightbulb-o',
      'fa-exchange',
      'fa-cloud-download',
      'fa-cloud-upload',
      'fa-user-md',
      'fa-stethoscope',
      'fa-suitcase',
      'fa-bell-o',
      'fa-coffee',
      'fa-cutlery',
      'fa-file-text-o',
      'fa-building-o',
      'fa-hospital-o',
      'fa-ambulance',
      'fa-medkit',
      'fa-fighter-jet',
      'fa-beer',
      'fa-h-square',
      'fa-plus-square',
      'fa-angle-double-left',
      'fa-angle-double-right',
      'fa-angle-double-up',
      'fa-angle-double-down',
      'fa-angle-left',
      'fa-angle-right',
      'fa-angle-up',
      'fa-angle-down',
      'fa-desktop',
      'fa-laptop',
      'fa-tablet',
      'fa-mobile',
      'fa-circle-o',
      'fa-quote-left',
      'fa-quote-right',
      'fa-spinner',
      'fa-circle',
      'fa-reply',
      'fa-github-alt',
      'fa-folder-o',
      'fa-folder-open-o',
      'fa-smile-o',
      'fa-frown-o',
      'fa-meh-o',
      'fa-gamepad',
      'fa-keyboard-o',
      'fa-flag-o',
      'fa-flag-checkered',
      'fa-terminal',
      'fa-code',
      'fa-reply-all',
      'fa-mail-reply-all',
      'fa-star-half-o',
      'fa-location-arrow',
      'fa-crop',
      'fa-code-fork',
      'fa-chain-broken',
      'fa-question',
      'fa-info',
      'fa-exclamation',
      'fa-superscript',
      'fa-subscript',
      'fa-eraser',
      'fa-puzzle-piece',
      'fa-microphone',
      'fa-microphone-slash',
      'fa-shield',
      'fa-calendar-o',
      'fa-fire-extinguisher',
      'fa-rocket',
      'fa-maxcdn',
      'fa-chevron-circle-left',
      'fa-chevron-circle-right',
      'fa-chevron-circle-up',
      'fa-chevron-circle-down',
      'fa-html5',
      'fa-css3',
      'fa-anchor',
      'fa-unlock-alt',
      'fa-bullseye',
      'fa-ellipsis-h',
      'fa-ellipsis-v',
      'fa-rss-square',
      'fa-play-circle',
      'fa-ticket',
      'fa-minus-square',
      'fa-minus-square-o',
      'fa-level-up',
      'fa-level-down',
      'fa-check-square',
      'fa-pencil-square',
      'fa-external-link-square',
      'fa-share-square',
      'fa-compass',
      'fa-caret-square-o-down',
      'fa-caret-square-o-up',
      'fa-caret-square-o-right',
      'fa-eur',
      'fa-gbp',
      'fa-usd',
      'fa-inr',
      'fa-jpy',
      'fa-rub',
      'fa-krw',
      'fa-btc',
      'fa-file',
      'fa-file-text',
      'fa-sort-alpha-asc',
      'fa-sort-alpha-desc',
      'fa-sort-amount-asc',
      'fa-sort-amount-desc',
      'fa-sort-numeric-asc',
      'fa-sort-numeric-desc',
      'fa-thumbs-up',
      'fa-thumbs-down',
      'fa-youtube-square',
      'fa-youtube',
      'fa-xing',
      'fa-xing-square',
      'fa-youtube-play',
      'fa-dropbox',
      'fa-stack-overflow',
      'fa-instagram',
      'fa-flickr',
      'fa-adn',
      'fa-bitbucket',
      'fa-bitbucket-square',
      'fa-tumblr',
      'fa-tumblr-square',
      'fa-long-arrow-down',
      'fa-long-arrow-up',
      'fa-long-arrow-left',
      'fa-long-arrow-right',
      'fa-apple',
      'fa-windows',
      'fa-android',
      'fa-linux',
      'fa-dribbble',
      'fa-skype',
      'fa-foursquare',
      'fa-trello',
      'fa-female',
      'fa-male',
      'fa-gittip',
      'fa-sun-o',
      'fa-moon-o',
      'fa-archive',
      'fa-bug',
      'fa-vk',
      'fa-weibo',
      'fa-renren',
      'fa-pagelines',
      'fa-stack-exchange',
      'fa-arrow-circle-o-right',
      'fa-arrow-circle-o-left',
      'fa-caret-square-o-left',
      'fa-dot-circle-o',
      'fa-wheelchair',
      'fa-vimeo-square',
      'fa-try',
      'fa-plus-square-o',
      'fa-space-shuttle',
      'fa-slack',
      'fa-envelope-square',
      'fa-wordpress',
      'fa-openid',
      'fa-institution',
      'fa-bank',
      'fa-university',
      'fa-mortar-board',
      'fa-graduation-cap',
      'fa-yahoo',
      'fa-google',
      'fa-reddit',
      'fa-reddit-square',
      'fa-stumbleupon-circle',
      'fa-stumbleupon',
      'fa-delicious',
      'fa-digg',
      'fa-pied-piper-square',
      'fa-pied-piper',
      'fa-pied-piper-alt',
      'fa-drupal',
      'fa-joomla',
      'fa-language',
      'fa-fax',
      'fa-building',
      'fa-child',
      'fa-paw',
      'fa-spoon',
      'fa-cube',
      'fa-cubes',
      'fa-behance',
      'fa-behance-square',
      'fa-steam',
      'fa-steam-square',
      'fa-recycle',
      'fa-automobile',
      'fa-car',
      'fa-cab',
      'fa-tree',
      'fa-spotify',
      'fa-deviantart',
      'fa-soundcloud',
      'fa-database',
      'fa-file-pdf-o',
      'fa-file-word-o',
      'fa-file-excel-o',
      'fa-file-powerpoint-o',
      'fa-file-photo-o',
      'fa-file-picture-o',
      'fa-file-image-o',
      'fa-file-zip-o',
      'fa-file-archive-o',
      'fa-file-sound-o',
      'fa-file-movie-o',
      'fa-file-code-o',
      'fa-vine',
      'fa-codepen',
      'fa-jsfiddle',
      'fa-life-bouy',
      'fa-support',
      'fa-life-ring',
      'fa-circle-o-notch',
      'fa-rebel',
      'fa-empire',
      'fa-git-square',
      'fa-git',
      'fa-hacker-news',
      'fa-tencent-weibo',
      'fa-qq',
      'fa-wechat',
      'fa-send',
      'fa-paper-plane',
      'fa-send-o',
      'fa-paper-plane-o',
      'fa-history',
      'fa-circle-thin',
      'fa-header',
      'fa-paragraph',
      'fa-sliders',
      'fa-share-alt',
      'fa-share-alt-square',
      'fa-bomb',
    );
  }

  private function listColors() {
    return array(
      null,
      'bluegrey',
      'white',
      'red',
      'orange',
      'yellow',
      'green',
      'blue',
      'sky',
      'indigo',
      'violet',
      'lightgreytext',
      'lightbluetext',
    );
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

    $colors = $this->listColors();
    $trans = $this->listTransforms();
    $fas = $this->listFontAwesome();

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

    $logins = array(
      'Asana',
      'Dropbox',
      'Google',
      'Github');
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

    $layout4 = id(new PHUIBoxView())
      ->appendChild(array($card1, $card2, $card3, $card4, $card5))
      ->addMargin(PHUI::MARGIN_MEDIUM);

    $layout5 = id(new PHUIBoxView())
      ->appendChild($loginview)
      ->addMargin(PHUI::MARGIN_MEDIUM);

    $fa_link = phutil_tag(
      'a',
      array(
        'href' => 'http://fontawesome.io'
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

    $wrap4 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Payments'))
      ->appendChild($layout4);

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
          $wrap4,
          $wrap5
        ));
        }
}
