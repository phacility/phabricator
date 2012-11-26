<?php

final class CeleritySpriteGenerator {

  public function buildIconSheet() {
    $icons = $this->getDirectoryList('icons_1x');

    $colors = array(
      '',
      'grey',
      'white',
    );

    $scales = array(
      '1x'  => 1,
      '2x'  => 2,
    );

    $template = id(new PhutilSprite())
      ->setSourceSize(14, 14);

    $sprites = array();
    foreach ($colors as $color) {
      foreach ($icons as $icon) {
        $prefix = 'icons_';
        if (strlen($color)) {
          $prefix .= $color.'_';
        }

        $suffix = '';
        if (strlen($color)) {
          $suffix = '-'.$color;
        }

        $sprite = id(clone $template)
          ->setName('action-'.$icon.$suffix);

        if ($color == 'white') {
          $sprite->setTargetCSS(
            '.device-desktop .phabricator-action-view:hover .action-'.$icon);
        } else {
          $sprite->setTargetCSS('.action-'.$icon.$suffix);
        }

        foreach ($scales as $scale_key => $scale) {
          $path = $this->getPath($prefix.$scale_key.'/'.$icon.'.png');
          $sprite->setSourceFile($path, $scale);
        }
        $sprites[] = $sprite;
      }
    }

    $remarkup_icons = $this->getDirectoryList('remarkup_1x');
    foreach ($remarkup_icons as $icon) {
      $prefix = 'remarkup_';

      // Strip 'text_' from these file names.
      $class_name = substr($icon, 5);

      $sprite = id(clone $template)
        ->setName('remarkup-assist-'.$icon)
        ->setTargetCSS('.remarkup-assist-'.$class_name);
      foreach ($scales as $scale_key => $scale) {
        $path = $this->getPath($prefix.$scale_key.'/'.$icon.'.png');
        $sprite->setSourceFile($path, $scale);
      }
      $sprites[] = $sprite;
    }

    $sheet = $this->buildSheet('icon');
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
          "Expected file '{$image}' in '{$path}' to be a sprite source ".
          "ending in '.png'.");
      }
      $result[] = substr($image, 0, -4);
    }

    return $result;
  }

  private function buildSheet($name) {
    $sheet = new PhutilSpriteSheet();

    $at = '@';
    $sheet->setCSSHeader(<<<EOCSS
/**
 * @provides sprite-{$name}-css
 * {$at}generated
 */

.sprite-{$name} {
  background-image: url(/rsrc/image/sprite-{$name}.png);
  background-repeat: no-repeat;
}

@media
only screen and (min-device-pixel-ratio: 1.5),
only screen and (-webkit-min-device-pixel-ratio: 1.5) {
  .sprite-{$name} {
    background-image: url(/rsrc/image/sprite-{$name}-X2.png);
    background-size: {X}px {Y}px;
  }
}
EOCSS
);

    return $sheet;
  }
}


