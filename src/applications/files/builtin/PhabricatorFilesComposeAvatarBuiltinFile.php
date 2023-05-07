<?php

final class PhabricatorFilesComposeAvatarBuiltinFile
  extends PhabricatorFilesBuiltinFile {

  private $icon;
  private $color;
  private $border;

  private $maps = array();

  const VERSION = 'v1';

  public function updateUser(PhabricatorUser $user) {
    $username = $user->getUsername();

    $image_map = $this->getMap('image');
    $initial = phutil_utf8_strtoupper(substr($username, 0, 1));
    $pack = $this->pickMap('pack', $username);
    $icon = "alphanumeric/{$pack}/{$initial}.png";
    if (!isset($image_map[$icon])) {
      $icon = "alphanumeric/{$pack}/_default.png";
    }

    $border = $this->pickMap('border', $username);
    $color = $this->pickMap('color', $username);

    $data = $this->composeImage($color, $icon, $border);
    $name = $this->getImageDisplayName($color, $icon, $border);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      $file = PhabricatorFile::newFromFileData(
        $data,
        array(
          'name' => $name,
          'profile' => true,
          'canCDN' => true,
        ));

      $user
        ->setDefaultProfileImagePHID($file->getPHID())
        ->setDefaultProfileImageVersion(self::VERSION)
        ->saveWithoutIndex();

    unset($unguarded);

    return $file;
  }

  private function getMap($map_key) {
    if (!isset($this->maps[$map_key])) {
      switch ($map_key) {
        case 'pack':
          $map = $this->newPackMap();
          break;
        case 'image':
          $map = $this->newImageMap();
          break;
        case 'color':
          $map = $this->newColorMap();
          break;
        case 'border':
          $map = $this->newBorderMap();
          break;
        default:
          throw new Exception(pht('Unknown map "%s".', $map_key));
      }
      $this->maps[$map_key] = $map;
    }

    return $this->maps[$map_key];
  }

  private function pickMap($map_key, $username) {
    $map = $this->getMap($map_key);
    $seed = $username.'_'.$map_key;
    $key = PhabricatorHash::digestToRange($seed, 0, count($map) - 1);
    return $map[$key];
  }


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

  public function setBorder($border) {
    $this->border = $border;
    return $this;
  }

  public function getBorder() {
    return $this->border;
  }

  public function getBuiltinFileKey() {
    $icon = $this->getIcon();
    $color = $this->getColor();
    $border = implode(',', $this->getBorder());
    $desc = "compose(icon={$icon}, color={$color}, border={$border}";
    $hash = PhabricatorHash::digestToLength($desc, 40);
    return "builtin:{$hash}";
  }

  public function getBuiltinDisplayName() {
    return $this->getImageDisplayName(
      $this->getIcon(),
      $this->getColor(),
      $this->getBorder());
  }

  private function getImageDisplayName($icon, $color, $border) {
    $border = implode(',', $border);
    return "{$icon}-{$color}-{$border}.png";
  }

  public function loadBuiltinFileData() {
    return $this->composeImage(
      $this->getColor(),
      $this->getIcon(),
      $this->getBorder());
  }

  private function composeImage($color, $image, $border) {
    // If we don't have the GD extension installed, just return a static
    // default profile image rather than trying to compose a dynamic one.
    if (!function_exists('imagecreatefromstring')) {
      $root = dirname(phutil_get_library_root('phabricator'));
      $default_path = $root.'/resources/builtin/profile.png';
      return Filesystem::readFile($default_path);
    }

    $color_const = hexdec(trim($color, '#'));
    $true_border = self::rgba2gd($border);
    $image_map = $this->getMap('image');
    $data = Filesystem::readFile($image_map[$image]);

    $img = imagecreatefromstring($data);

    // 4 pixel border at 50x50, 32 pixel border at 400x400
    $canvas = imagecreatetruecolor(400, 400);

    $image_fill = imagefill($canvas, 0, 0, $color_const);
    if (!$image_fill) {
      throw new Exception(
        pht('Failed to save builtin avatar image data (imagefill).'));
    }

    $border_thickness = imagesetthickness($canvas, 64);
    if (!$border_thickness) {
      throw new Exception(
        pht('Failed to save builtin avatar image data (imagesetthickness).'));
    }

    $image_rectangle = imagerectangle($canvas, 0, 0, 400, 400, $true_border);
    if (!$image_rectangle) {
      throw new Exception(
        pht('Failed to save builtin avatar image data (imagerectangle).'));
    }

    $image_copy = imagecopy($canvas, $img, 0, 0, 0, 0, 400, 400);
    if (!$image_copy) {
      throw new Exception(
        pht('Failed to save builtin avatar image data (imagecopy).'));
    }

    return PhabricatorImageTransformer::saveImageDataInAnyFormat(
      $canvas,
      'image/png');
  }

  private static function rgba2gd(array $rgba) {
    $r = (int)$rgba[0];
    $g = (int)$rgba[1];
    $b = (int)$rgba[2];
    $a = (int)$rgba[3];
    $a = (1 - $a) * 255;
    return ($a << 24) | ($r << 16) | ($g << 8) | $b;
  }

  private function newImageMap() {
    $root = dirname(phutil_get_library_root('phabricator'));
    $root = $root.'/resources/builtin/alphanumeric/';

    $map = array();
    $list = id(new FileFinder($root))
      ->withType('f')
      ->withFollowSymlinks(true)
      ->find();

    foreach ($list as $file) {
      $map['alphanumeric/'.$file] = $root.$file;
    }

    return $map;
  }

  private function newPackMap() {
    $root = dirname(phutil_get_library_root('phabricator'));
    $root = $root.'/resources/builtin/alphanumeric/';

    $map = id(new FileFinder($root))
      ->withType('d')
      ->withFollowSymlinks(false)
      ->find();
    $map = array_values($map);

    return $map;
  }

  private function newBorderMap() {
    return array(
      array(0, 0, 0, 0),
      array(0, 0, 0, 0.3),
      array(255, 255, 255, 0.4),
      array(255, 255, 255, 0.7),
    );
  }

  private function newColorMap() {
    // Via: http://tools.medialab.sciences-po.fr/iwanthue/

    return array(
      '#335862',
      '#2d5192',
      '#3c5da0',
      '#99cd86',
      '#704889',
      '#5ac59e',
      '#984060',
      '#33d4d1',
      '#9c4050',
      '#20d8fd',
      '#944937',
      '#4bd0e3',
      '#a25542',
      '#4eb4f3',
      '#6da8ec',
      '#545608',
      '#829ce5',
      '#68681d',
      '#607bc2',
      '#4b69ad',
      '#236ead',
      '#31a0de',
      '#4f8ed0',
      '#846f2a',
      '#bdb0f0',
      '#518342',
      '#9166aa',
      '#5e904e',
      '#f79dcc',
      '#158e6b',
      '#e189b7',
      '#3ba984',
      '#a85582',
      '#4cccb7',
      '#863d67',
      '#84c08c',
      '#7f4c7f',
      '#a1bb7a',
      '#65558f',
      '#445082',
      '#c9ca8e',
      '#265582',
      '#f4b189',
      '#265582',
      '#40b8e1',
      '#814a28',
      '#80c8f6',
      '#cf7b5d',
      '#1db5c7',
      '#c0606e',
      '#299a89',
      '#ef8ead',
      '#296437',
      '#d39edb',
      '#507436',
      '#b888c9',
      '#476025',
      '#9987c5',
      '#7867a3',
      '#769b5a',
      '#c46e9d',
      '#437d4e',
      '#d17492',
      '#115e41',
      '#ec8794',
      '#297153',
      '#d67381',
      '#57c2c3',
      '#bc607f',
      '#86ceac',
      '#7e3e53',
      '#72c8b8',
      '#884349',
      '#45a998',
      '#faa38c',
      '#265582',
      '#265582',
      '#e4b788',
      '#265582',
      '#bbbc81',
      '#265582',
      '#ccb781',
      '#265582',
      '#eb957f',
      '#15729c',
      '#cf996f',
      '#369bc5',
      '#b6685d',
      '#2da0a1',
      '#d38275',
      '#217e70',
      '#ec9da1',
      '#146268',
      '#e8aa95',
      '#3c6796',
      '#8da667',
      '#935f93',
      '#69a573',
      '#ae78ad',
      '#569160',
      '#d898be',
      '#8eb4e8',
      '#5e622c',
      '#929ad3',
      '#6c8548',
      '#576196',
      '#aed0a0',
      '#694e79',
      '#9abb8d',
      '#8c5175',
      '#6bb391',
      '#8b4a5f',
      '#519878',
      '#ae7196',
      '#3d8465',
      '#e69eb3',
      '#48663d',
      '#cdaede',
      '#71743d',
      '#63acda',
      '#7b5d30',
      '#66bed6',
      '#3585b0',
      '#5880b0',
      '#739acc',
      '#48a3ba',
      '#9d565b',
      '#7fc4ca',
      '#99566b',
      '#94cabf',
      '#7b4b49',
      '#b1c8eb',
      '#4e5632',
      '#ecb2c3',
      '#2d6158',
      '#cf8287',
      '#25889f',
      '#b2696d',
      '#6bafb6',
      '#8c5744',
      '#84b9d6',
      '#9db3d6',
      '#777cad',
      '#826693',
      '#86a779',
      '#9d7fad',
      '#b193c2',
      '#547348',
      '#d5adcb',
      '#3f674d',
      '#c98398',
      '#66865a',
      '#b2add6',
      '#5a623d',
      '#9793bb',
      '#3c5472',
      '#d5c5a1',
      '#5e5a7f',
      '#2c647e',
      '#d8b194',
      '#49607f',
      '#c7b794',
      '#335862',
      '#e3aba7',
      '#335862',
      '#d9b9ad',
      '#335862',
      '#c48975',
      '#347b81',
      '#ad697e',
      '#799a6d',
      '#916b88',
      '#69536b',
      '#b4c4ad',
      '#845865',
      '#96b89d',
      '#706d92',
      '#9aa27a',
      '#5b7292',
      '#bc967b',
      '#417792',
      '#ce9793',
      '#335862',
      '#c898a5',
      '#527a5f',
      '#b38ba9',
      '#648d72',
      '#986b78',
      '#79afa4',
      '#966461',
      '#50959b',
      '#b27d7a',
      '#335862',
      '#335862',
      '#bcadc4',
      '#706343',
      '#749ebc',
      '#8c6a50',
      '#92b8c4',
      '#758cad',
      '#868e67',
      '#335862',
      '#335862',
      '#335862',
      '#ac7e8b',
      '#77a185',
      '#807288',
      '#636f51',
      '#a192a9',
      '#467a70',
      '#9b7d73',
      '#335862',
      '#335862',
      '#8c9c85',
      '#335862',
      '#81645a',
      '#5f9489',
      '#335862',
      '#789da8',
      '#335862',
      '#72826c',
      '#335862',
      '#5c8596',
      '#335862',
      '#456a74',
      '#335862',
      '#335862',
      '#335862',
    );
  }

}
