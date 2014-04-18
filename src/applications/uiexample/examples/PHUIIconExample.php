<?php

final class PHUIIconExample extends PhabricatorUIExample {

  public function getName() {
    return 'Icons and Images';
  }

  public function getDescription() {
    return 'Easily render icons or images with links and sprites.';
  }

  private function listHalflings() {
    return array (
      'glass',
      'music',
      'search',
      'envelope',
      'heart',
      'star',
      'star-empty',
      'user',
      'film',
      'th-large',
      'th',
      'th-list',
      'ok',
      'remove',
      'zoom-in',
      'zoom-out',
      'off',
      'signal',
      'cog',
      'trash',
      'home',
      'file',
      'time',
      'road',
      'download-alt',
      'download',
      'upload',
      'inbox',
      'play-circle',
      'repeat',
      'refresh',
      'list-alt',
      'lock',
      'flag',
      'headphones',
      'volume-off',
      'volume-down',
      'volume-up',
      'qrcode',
      'barcode',
      'tag',
      'tags',
      'book',
      'bookmark',
      'print',
      'camera',
      'font',
      'bold',
      'italic',
      'text-height',
      'text-width',
      'align-left',
      'align-center',
      'align-right',
      'align-justify',
      'list',
      'indent-left',
      'indent-right',
      'facetime-video',
      'picture',
      'pencil',
      'map-marker',
      'adjust',
      'tint',
      'edit',
      'share',
      'check',
      'move',
      'step-backward',
      'fast-backward',
      'backward',
      'play',
      'pause',
      'stop',
      'forward',
      'fast-forward',
      'step-forward',
      'eject',
      'chevron-left',
      'chevron-right',
      'plus-sign',
      'minus-sign',
      'remove-sign',
      'ok-sign',
      'question-sign',
      'info-sign',
      'screenshot',
      'remove-circle',
      'ok-circle',
      'ban-circle',
      'arrow-left',
      'arrow-right',
      'arrow-up',
      'arrow-down',
      'share-alt',
      'resize-full',
      'resize-small',
      'plus',
      'minus',
      'asterisk',
      'exclamation-sign',
      'gift',
      'leaf',
      'fire',
      'eye-open',
      'eye-close',
      'warning-sign',
      'plane',
      'calendar',
      'random',
      'comments',
      'magnet',
      'chevron-up',
      'chevron-down',
      'retweet',
      'shopping-cart',
      'folder-close',
      'folder-open',
      'resize-vertical',
      'resize-horizontal',
      'hdd',
      'bullhorn',
      'bell',
      'certificate',
      'thumbs-up',
      'thumbs-down',
      'hand-right',
      'hand-left',
      'hand-top',
      'hand-down',
      'circle-arrow-right',
      'circle-arrow-left',
      'circle-arrow-top',
      'circle-arrow-down',
      'globe',
      'wrench',
      'tasks',
      'filter',
      'briefcase',
      'fullscreen',
      'dashboard',
      'paperclip',
      'heart-empty',
      'link',
      'phone',
      'pushpin',
      'euro',
      'usd',
      'gbp',
      'sort',
      'sort-by-alphabet',
      'sort-by-alphabet-alt',
      'sort-by-order',
      'sort-by-order-alt',
      'sort-by-attributes',
      'sort-by-attributes-alt',
      'unchecked',
      'expand',
      'collapse',
      'collapse-top',
      'log_in',
      'flash',
      'log_out',
      'new_window',
      'record',
      'save',
      'open',
      'saved',
      'import',
      'export',
      'send',
      'floppy_disk',
      'floppy_saved',
      'floppy_remove',
      'floppy_save',
      'floppy_open',
      'credit_card',
      'transfer',
      'cutlery',
      'header',
      'compressed',
      'earphone',
      'phone_alt',
      'tower',
      'stats',
      'sd_video',
      'hd_video',
      'subtitles',
      'sound_stereo',
      'sound_dolby',
      'sound_5_1',
      'sound_6_1',
      'sound_7_1',
      'copyright_mark',
      'registration_mark',
      'cloud',
      'cloud_download',
      'cloud_upload',
      'tree_conifer',
      'tree_deciduous',
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
    );
  }

  public function renderExample() {

    $colors = $this->listColors();
    $glyphs = $this->listHalflings();
    $gicons = array();
    foreach ($glyphs as $glyph) {
      $gicons[] = id(new PHUIIconView())
        ->addClass('phui-halfling-name')
        ->setHalfling($glyph)
        ->appendChild($glyph);
    }
    $cicons = array();
    foreach ($colors as $color) {
      $cicons[] = id(new PHUIIconView())
        ->addClass('phui-halfling-color')
        ->setHalfling('tag', $color)
        ->appendChild(pht('tag %s', $color));
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

    $layout_gicons = id(new PHUIBoxView())
      ->appendChild($gicons)
      ->addMargin(PHUI::MARGIN_LARGE);

    $layout_cicons = id(new PHUIBoxView())
      ->appendChild($cicons)
      ->addMargin(PHUI::MARGIN_LARGE);

    $layout1 = id(new PHUIBoxView())
      ->appendChild($actionview)
      ->addMargin(PHUI::MARGIN_MEDIUM);

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

    $halflings = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Glyphicon Halflings'))
      ->appendChild($layout_gicons);

    $halflings_color = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Halflings Colors'))
      ->appendChild($layout_cicons);

    $wrap1 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Action Icons!'))
      ->appendChild($layout1);

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
          $halflings,
          $halflings_color,
          $wrap1,
          $wrap2,
          $wrap3,
          $wrap4,
          $wrap5
        ));
        }
}
