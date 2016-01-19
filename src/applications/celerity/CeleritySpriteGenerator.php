<?php

final class CeleritySpriteGenerator extends Phobject {

  public function buildMenuSheet() {
    $sprites = array();

    $colors = array(
      'dark',
      'light',
    );

    $sources = array();
    foreach ($colors as $color) {
      $sources[$color.'-logo'] = array(
        'x' => 96,
        'y' => 40,
        'css' => '.'.$color.'-logo',
      );
      $sources[$color.'-eye'] = array(
        'x' => 40,
        'y' => 40,
        'css' => '.'.$color.'-eye',
      );
    }

    $scales = array(
      '1x' => 1,
      '2x' => 2,
    );

    $template = new PhutilSprite();
    foreach ($sources as $name => $spec) {
      $sprite = id(clone $template)
        ->setName($name)
        ->setSourceSize($spec['x'], $spec['y'])
        ->setTargetCSS($spec['css']);

      foreach ($scales as $scale_name => $scale) {
        $path = 'menu_'.$scale_name.'/'.$name.'.png';
        $path = $this->getPath($path);

        $sprite->setSourceFile($path, $scale);
      }
      $sprites[] = $sprite;
    }

    $sheet = $this->buildSheet('menu', true);
    $sheet->setScales($scales);
    foreach ($sprites as $sprite) {
      $sheet->addSprite($sprite);
    }

    return $sheet;
  }

  public function buildTokenSheet() {
    $icons = $this->getDirectoryList('tokens_1x');
    $scales = array(
      '1x' => 1,
      '2x' => 2,
    );
    $template = id(new PhutilSprite())
      ->setSourceSize(16, 16);

    $sprites = array();
    $prefix = 'tokens_';
    foreach ($icons as $icon) {
      $sprite = id(clone $template)
        ->setName('tokens-'.$icon)
        ->setTargetCSS('.tokens-'.$icon);

      foreach ($scales as $scale_key => $scale) {
        $path = $this->getPath($prefix.$scale_key.'/'.$icon.'.png');
        $sprite->setSourceFile($path, $scale);
      }
      $sprites[] = $sprite;
    }

    $sheet = $this->buildSheet('tokens', true);
    $sheet->setScales($scales);
    foreach ($sprites as $sprite) {
      $sheet->addSprite($sprite);
    }

    return $sheet;
  }

  public function buildLoginSheet() {
    $icons = $this->getDirectoryList('login_1x');
    $scales = array(
      '1x' => 1,
      '2x' => 2,
    );
    $template = id(new PhutilSprite())
      ->setSourceSize(28, 28);

    $sprites = array();
    $prefix = 'login_';
    foreach ($icons as $icon) {
      $sprite = id(clone $template)
        ->setName('login-'.$icon)
        ->setTargetCSS('.login-'.$icon);

      foreach ($scales as $scale_key => $scale) {
        $path = $this->getPath($prefix.$scale_key.'/'.$icon.'.png');
        $sprite->setSourceFile($path, $scale);
      }
      $sprites[] = $sprite;
    }

    $sheet = $this->buildSheet('login', true);
    $sheet->setScales($scales);
    foreach ($sprites as $sprite) {
      $sheet->addSprite($sprite);
    }

    return $sheet;
  }

  private function getPath($to_path = null) {
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/resources/sprite/'.$to_path;
  }

  private function getDirectoryList($dir) {
    $path = $this->getPath($dir);

    $result = array();

    $images = Filesystem::listDirectory($path, $include_hidden = false);
    foreach ($images as $image) {
      if (!preg_match('/\.png$/', $image)) {
        throw new Exception(
          pht(
            "Expected file '%s' in '%s' to be a sprite source ending in '%s'.",
            $image,
            $path,
            '.png'));
      }
      $result[] = substr($image, 0, -4);
    }

    return $result;
  }

  private function buildSheet(
    $name,
    $has_retina,
    $type = null,
    $extra_css = '') {

    $sheet = new PhutilSpriteSheet();

    $at = '@';

    switch ($type) {
      case PhutilSpriteSheet::TYPE_STANDARD:
      default:
        $type = PhutilSpriteSheet::TYPE_STANDARD;
        $repeat_rule = 'no-repeat';
        break;
      case PhutilSpriteSheet::TYPE_REPEAT_X:
        $repeat_rule = 'repeat-x';
        break;
      case PhutilSpriteSheet::TYPE_REPEAT_Y:
        $repeat_rule = 'repeat-y';
        break;
    }

    $retina_rules = null;
    if ($has_retina) {
      $retina_rules = <<<EOCSS
@media
only screen and (min-device-pixel-ratio: 1.5),
only screen and (-webkit-min-device-pixel-ratio: 1.5),
only screen and (min-resolution: 1.5dppx) {
  .sprite-{$name}{$extra_css} {
    background-image: url(/rsrc/image/sprite-{$name}-X2.png);
    background-size: {X}px {Y}px;
  }
}
EOCSS;
    }

    $sheet->setSheetType($type);
    $sheet->setCSSHeader(<<<EOCSS
/**
 * @provides sprite-{$name}-css
 * {$at}generated
 */

.sprite-{$name}{$extra_css} {
  background-image: url(/rsrc/image/sprite-{$name}.png);
  background-repeat: {$repeat_rule};
}

{$retina_rules}

EOCSS
);

    return $sheet;
  }
}
