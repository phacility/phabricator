<?php

final class PhabricatorFilesComposeIconBuiltinFile
  extends PhabricatorFilesBuiltinFile {

  private $icon;
  private $color;

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function getColor() {
    return $this->color;
  }

  public function getBuiltinFileKey() {
    $icon = $this->getIcon();
    $color = $this->getColor();
    $desc = "compose(icon={$icon}, color={$color})";
    $hash = PhabricatorHash::digestToLength($desc, 40);
    return "builtin:{$hash}";
  }

  public function getBuiltinDisplayName() {
    $icon = $this->getIcon();
    $color = $this->getColor();
    return "{$icon}-{$color}.png";
  }

  public function loadBuiltinFileData() {
    return $this->composeImage($this->getColor(), $this->getIcon());
  }

  public static function getAllIcons() {
    $root = dirname(phutil_get_library_root('phabricator'));
    $root = $root.'/resources/builtin/projects/';

    $quips = self::getIconQuips();

    $map = array();
    $list = Filesystem::listDirectory($root, $include_hidden = false);
    foreach ($list as $file) {
      $short = preg_replace('/\.png$/', '', $file);

      $map[$short] = array(
        'path' => $root.$file,
        'quip' => idx($quips, $short, $short),
      );
    }

    return $map;
  }

  public static function getAllColors() {
    $colors = id(new CelerityResourceTransformer())
      ->getCSSVariableMap();

    $colors = array_select_keys(
      $colors,
      array(
        'red',
        'orange',
        'yellow',
        'green',
        'blue',
        'sky',
        'indigo',
        'violet',
        'pink',
        'charcoal',
        'backdrop',
      ));

    $quips = self::getColorQuips();

    $map = array();
    foreach ($colors as $name => $color) {
      $map[$name] = array(
        'color' => $color,
        'quip' => idx($quips, $name, $name),
      );
    }

    return $map;
  }

  private function composeImage($color, $icon) {
    $color_map = self::getAllColors();
    $color = idx($color_map, $color);
    if (!$color) {
      $fallback = 'backdrop';
      $color = idx($color_map, $fallback);
      if (!$color) {
        throw new Exception(
          pht(
            'Fallback compose color ("%s") does not exist!',
            $fallback));
      }
    }

    $color_hex = idx($color, 'color');
    $color_const = hexdec(trim($color_hex, '#'));

    $icon_map = self::getAllIcons();
    $icon = idx($icon_map, $icon);
    if (!$icon) {
      $fallback = 'fa-umbrella';
      $icon = idx($icon_map, $fallback);
      if (!$icon) {
        throw new Exception(
          pht(
            'Fallback compose icon ("%s") does not exist!',
            $fallback));
      }
    }

    $path = idx($icon, 'path');
    $data = Filesystem::readFile($path);

    $icon_img = imagecreatefromstring($data);

    $canvas = imagecreatetruecolor(200, 200);
    imagefill($canvas, 0, 0, $color_const);
    imagecopy($canvas, $icon_img, 0, 0, 0, 0, 200, 200);

    return PhabricatorImageTransformer::saveImageDataInAnyFormat(
      $canvas,
      'image/png');
  }

  private static function getIconQuips() {
    return array(
      'fa-android' => pht('Friendly Robot'),
      'fa-apple' => pht('Friendly Fruit'),
      'fa-beer' => pht('Liquid Carbs'),
      'fa-bomb' => pht('Boom!'),
      'fa-book' => pht('Read Me'),
      'fa-briefcase' => pht('Briefcase'),
      'fa-bug' => pht('Bug'),
      'fa-building' => pht('Company'),
      'fa-calendar' => pht('Deadline'),
      'fa-camera-retro' => pht('Leica Enthusiast'),
      'fa-chrome' => pht('Shiny'),
      'fa-cloud' => pht('The Cloud'),
      'fa-coffee' => pht('Go Juice'),
      'fa-comments' => pht('Cartoon Captions'),
      'fa-credit-card' => pht('Accounting'),
      'fa-database' => pht('Stack of Pancakes'),
      'fa-desktop' => pht('Cardboard Box'),
      'fa-diamond' => pht('Isometric-Hexoctahedral'),
      'fa-empire' => pht('Bad Guys'),
      'fa-envelope' => pht('Communication'),
      'fa-facebook' => pht('College Site'),
      'fa-fax' => pht('Communication Device'),
      'fa-film' => pht('Physical Film'),
      'fa-firefox' => pht('Blake Ross'),
      'fa-flag-checkered' => pht('Goal'),
      'fa-flask' => pht('Experimental'),
      'fa-folder' => pht('Folder'),
      'fa-gamepad' => pht('Half-Life 3 Confirmed'),
      'fa-gears' => pht('Mechanical'),
      'fa-google' => pht('Car Company'),
      'fa-hand-peace-o' => pht('Peace'),
      'fa-hashtag' => pht('Not Slack'),
      'fa-heart' => pht('Myocardial Infarction'),
      'fa-internet-explorer' => pht('Now Just Edge'),
      'fa-key' => pht('Primitive Security'),
      'fa-legal' => pht('Hired Protection'),
      'fa-linux' => pht('M\'Lady'),
      'fa-lock' => pht('Policy'),
      'fa-map-marker' => pht('Destination Beacon'),
      'fa-microphone' => pht('Podcasting'),
      'fa-mobile' => pht('Tiny Pocket Cat Meme Machine'),
      'fa-money' => pht('1 of 99 Problems'),
      'fa-phone' => pht('Grandma Uses This'),
      'fa-pie-chart' => pht('Not Actually Edible'),
      'fa-rebel' => pht('Good Guys'),
      'fa-reddit-alien' => pht('Updoot In 5 Seconds'),
      'fa-safari' => pht('Fruit Exploration'),
      'fa-search' => pht('Dust Detector'),
      'fa-server' => pht('Heating Units'),
      'fa-shopping-cart' => pht('Buy Stuff'),
      'fa-sitemap' => pht('Sitemap'),
      'fa-star' => pht('The More You Know'),
      'fa-tablet' => pht('Cellular Telephone For Giants'),
      'fa-tag' => pht('You\'re It'),
      'fa-tags' => pht('Tags'),
      'fa-trash-o' => pht('Garbage'),
      'fa-truck' => pht('Release'),
      'fa-twitter' => pht('Bird Stencil'),
      'fa-umbrella' => pht('An Umbrella'),
      'fa-university' => pht('School'),
      'fa-user-secret' => pht('Shhh'),
      'fa-user' => pht('Individual'),
      'fa-users' => pht('Team'),
      'fa-warning' => pht('No Caution Required, Everything Looks Safe'),
      'fa-wheelchair' => pht('Accessibility'),
      'fa-windows' => pht('Windows'),
    );
  }

  private static function getColorQuips() {
    return array(
      'red' => pht('Verbillion'),
      'orange' => pht('Navel Orange'),
      'yellow' => pht('Prim Goldenrod'),
      'green' => pht('Lustrous Verdant'),
      'blue' => pht('Tropical Deep'),
      'sky' => pht('Wide Open Sky'),
      'indigo' => pht('Pleated Khaki'),
      'violet' => pht('Aged Merlot'),
      'pink' => pht('Easter Bunny'),
      'charcoal' => pht('Gemstone'),
      'backdrop' => pht('Driven Snow'),
    );
  }

}
