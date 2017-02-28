<?php

final class PhabricatorFilesComposeAvatarBuiltinFile
  extends PhabricatorFilesBuiltinFile {

  private $icon;
  private $color;
  private $border;

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
    $border = $this->getBorder();
    $desc = "compose(icon={$icon}, color={$color}, border={$border}";
    $hash = PhabricatorHash::digestToLength($desc, 40);
    return "builtin:{$hash}";
  }

  public function getBuiltinDisplayName() {
    $icon = $this->getIcon();
    $color = $this->getColor();
    $border = $this->getBorder();
    return "{$icon}-{$color}-{$border}.png";
  }

  public function loadBuiltinFileData() {
    return $this->composeImage(
      $this->getColor(), $this->getIcon(), $this->getBorder());
  }

  private function composeImage($color, $icon, $border) {
    // TODO
  }

  public static function getImageMap() {
    $root = dirname(phutil_get_library_root('phabricator'));
    $root = $root.'/resources/builtin/alphanumeric/';

    $map = array();
    $list = Filesystem::listDirectory($root, $include_hidden = false);
    foreach ($list as $file) {
      $key = 'alphanumeric/'.$file;
      $map[$key] = $root.$file;
    }

    return $map;
  }

  public static function getColorMap() {
    $map = array(
      '#335862',
      '#dfc47b',
      '#2d5192',
      '#c0bc6e',
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
      '#705412',
      '#6da8ec',
      '#545608',
      '#829ce5',
      '#68681d',
      '#607bc2',
      '#d1b66e',
      '#4b69ad',
      '#a4a154',
      '#236ead',
      '#daa969',
      '#31a0de',
      '#996f31',
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
      '#c2a962',
      '#445082',
      '#c9ca8e',
      '#265582',
      '#f4b189',
      '#265582',
      '#bd8f50',
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
      '#828136',
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
      '#ad954f',
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
      '#525620',
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
      '#a66c4e',
      '#3585b0',
      '#ba865c',
      '#5880b0',
      '#9b864d',
      '#739acc',
      '#9d764a',
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
      '#725238',
      '#9db3d6',
      '#816f3e',
      '#777cad',
      '#a6a86e',
      '#826693',
      '#86a779',
      '#9d7fad',
      '#8b8e55',
      '#b193c2',
      '#547348',
      '#d5adcb',
      '#3f674d',
      '#c98398',
      '#66865a',
      '#b2add6',
      '#5a623d',
      '#9793bb',
      '#bea975',
      '#3c5472',
      '#d5c5a1',
      '#5e5a7f',
      '#b09c68',
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
      '#aeb68d',
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
      '#b2a381',
      '#335862',
      '#bcadc4',
      '#706343',
      '#749ebc',
      '#8c6a50',
      '#92b8c4',
      '#a07d62',
      '#758cad',
      '#868e67',
      '#335862',
      '#b6978c',
      '#335862',
      '#9e8f6e',
      '#335862',
      '#ac7e8b',
      '#77a185',
      '#807288',
      '#636f51',
      '#a192a9',
      '#467a70',
      '#9b7d73',
      '#335862',
      '#8a7c5b',
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
    return $map;
  }

  public static function getBorderMap() {
    $map = array(
      'rgba(0,0,0,.3);',        // Darker
      'rgba(255,255,255,.5);',  // Lighter
    );
    return $map;
  }

}
