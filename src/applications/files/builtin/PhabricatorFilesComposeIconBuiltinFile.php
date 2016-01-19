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

    $canvas = imagecreatetruecolor(100, 100);
    imagefill($canvas, 0, 0, $color_const);
    imagecopy($canvas, $icon_img, 0, 0, 0, 0, 100, 100);

    return PhabricatorImageTransformer::saveImageDataInAnyFormat(
      $canvas,
      'image/png');
  }

  private static function getIconQuips() {
    return array(
      '8ball' => pht('Take a Risk'),
      'alien' => pht('Foreign Interface'),
      'announce' => pht('Louder is Better'),
      'art' => pht('Unique Snowflake'),
      'award' => pht('Shooting Star'),
      'bacon' => pht('Healthy Vegetables'),
      'bandaid' => pht('Durable Infrastructure'),
      'beer' => pht('Healthy Vegetable Juice'),
      'bomb' => pht('Imminent Success'),
      'briefcase' => pht('Adventure Pack'),
      'bug' => pht('Costumed Egg'),
      'calendar' => pht('Everyone Loves Meetings'),
      'cloud' => pht('Water Cycle'),
      'coffee' => pht('Half-Whip Nonfat Soy Latte'),
      'creditcard' => pht('Expense It'),
      'death' => pht('Calcium Promotes Bone Health'),
      'desktop' => pht('Magical Portal'),
      'dropbox' => pht('Cardboard Box'),
      'education' => pht('Debt'),
      'experimental' => pht('CAUTION: Dangerous Chemicals'),
      'facebook' => pht('Popular Social Network'),
      'facility' => pht('Pollution Solves Problems'),
      'film' => pht('Actual Physical Film'),
      'forked' => pht('You Can\'t Eat Soup'),
      'games' => pht('Serious Business'),
      'ghost' => pht('Haunted'),
      'gift' => pht('Surprise!'),
      'globe' => pht('Scanner Sweep'),
      'golf' => pht('Business Meeting'),
      'heart' => pht('Undergoing a Major Surgery'),
      'intergalactic' => pht('Jupiter'),
      'lock' => pht('Extremely Secret'),
      'mail' => pht('Oragami'),
      'martini' => pht('Healthy Olive Drink'),
      'medical' => pht('Medic!'),
      'mobile' => pht('Cellular Telephone'),
      'music' => pht("\xE2\x99\xAB"),
      'news' => pht('Actual Physical Newspaper'),
      'orgchart' => pht('It\'s Good to be King'),
      'peoples' => pht('Angel and Devil'),
      'piechart' => pht('Actual Physical Pie'),
      'poison' => pht('Healthy Bone Juice'),
      'putabirdonit' => pht('Put a Bird On It'),
      'radiate' => pht('Radiant Beauty'),
      'savings' => pht('Oink Oink'),
      'search' => pht('Sleuthing'),
      'shield' => pht('Royal Crest'),
      'speed' => pht('Slow and Steady'),
      'sprint' => pht('Fire Exit'),
      'star' => pht('The More You Know'),
      'storage' => pht('Stack of Pancakes'),
      'tablet' => pht('Cellular Telephone For Giants'),
      'travel' => pht('Pretty Clearly an Airplane'),
      'twitter' => pht('Bird Stencil'),
      'warning' => pht('No Caution Required, Everything Looks Safe'),
      'whale' => pht('Friendly Walrus'),
      'fa-flask' => pht('Experimental'),
      'fa-briefcase' => pht('Briefcase'),
      'fa-bug' => pht('Bug'),
      'fa-building' => pht('Company'),
      'fa-calendar' => pht('Deadline'),
      'fa-cloud' => pht('The Cloud'),
      'fa-credit-card' => pht('Accounting'),
      'fa-envelope' => pht('Communication'),
      'fa-flag-checkered' => pht('Goal'),
      'fa-folder' => pht('Folder'),
      'fa-group' => pht('Team'),
      'fa-lock' => pht('Policy'),
      'fa-tags' => pht('Tag'),
      'fa-trash-o' => pht('Garbage'),
      'fa-truck' => pht('Release'),
      'fa-umbrella' => pht('An Umbrella'),
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
