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

        $tcss = array();
        $tcss[] = '.action-'.$icon.$suffix;
        if ($color == 'white') {
          $tcss[] = '.device-desktop .phabricator-action-view:hover '.
            '.action-'.$icon;
          if ($icon == 'new') {
            // Hover state for the "+" icons on homepage tiles.
            $tcss[] = '.phabricator-application-launch-create:hover '.
                      '.phabricator-application-create-icon.action-new-grey';
          }
        }

        $sprite->setTargetCSS(implode(', ', $tcss));

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

    $sheet = $this->buildSheet('icon', true);
    $sheet->setScales($scales);
    foreach ($sprites as $sprite) {
      $sheet->addSprite($sprite);
    }

    return $sheet;
  }

  public function buildMenuSheet() {
    $sprites = array();

    $sources = array(
      'seen_read_all' => array(
        'x' => 18,
        'y' => 18,
        'css' =>
          '.alert-notifications .phabricator-main-menu-alert-icon',
      ),
      'seen_have_unread' => array(
        'x' => 18,
        'y' => 18,
        'css' =>
          '.alert-notifications:hover .phabricator-main-menu-alert-icon',
      ),
      'unseen_any' => array(
        'x' => 18,
        'y' => 18,
        'css' =>
          '.alert-notifications.alert-unread .phabricator-main-menu-alert-icon',
      ),
      'arrow-right' => array(
        'x' => 9,
        'y' => 31,
        'css' => '.phabricator-crumb-divider',
      ),
      'eye' => array(
        'x' => 24,
        'y' => 20,
        'css' => '.menu-icon-eye',
      ),
      'eye_blue' => array(
        'x' => 24,
        'y' => 20,
        'css' => '.menu-icon-eye-blue',
      ),
      'app' => array(
        'x' => 24,
        'y' => 20,
        'css' => '.menu-icon-app',
      ),
      'app_blue' => array(
        'x' => 24,
        'y' => 20,
        'css' => '.menu-icon-app-blue',
      ),
      'logo' => array(
        'x' => 139,
        'y' => 25,
        'css' => '.phabricator-main-menu-logo-image',
      ),
      'conf-off' => array(
        'x' => 18,
        'y' => 18,
        'css' =>
          '.alert-notifications .phabricator-main-menu-message-icon',
      ),
      'conf-hover' => array(
        'x' => 18,
        'y' => 18,
        'css' =>
          '.alert-notifications:hover .phabricator-main-menu-message-icon',
      ),
      'conf-unseen' => array(
        'x' => 18,
        'y' => 18,
        'css' =>
          '.alert-notifications.message-unread '.
          '.phabricator-main-menu-message-icon',
      ),
    );

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
    $tokens = $this->getDirectoryList('tokens_1x');

    $template = id(new PhutilSprite())
      ->setSourceSize(16, 16);

    $sprites = array();
    foreach ($tokens as $token) {
      $path = $this->getPath('tokens_1x/'.$token.'.png');

      $sprite = id(clone $template)
        ->setName('token-'.$token)
        ->setTargetCSS('.token-'.$token)
        ->setSourceFile($path, 1);

      $sprites[] = $sprite;
    }

    $sheet = $this->buildSheet('tokens', false);
    foreach ($sprites as $sprite) {
      $sheet->addSprite($sprite);
    }

    return $sheet;
  }


  public function buildConpherenceSheet() {
    $name = 'conpherence';
    $icons = $this->getDirectoryList($name.'_1x');
    $scales = array(
      '1x' => 1,
      '2x' => 2,
    );
    $template = id(new PhutilSprite())
      ->setSourceSize(32, 32);

    $sprites = array();
    foreach ($icons as $icon) {
      $color = preg_match('/_on/', $icon) ? 'on' : 'off';

      $prefix = $name.'_';

      $sprite = id(clone $template)
        ->setName($prefix.$icon);

      $tcss = array();
      $tcss[] = '.'.$prefix.$icon;
      if ($color == 'on') {
        $class = str_replace('_on', '_off', $prefix.$icon);
        $tcss[] = '.device-desktop .'.$class.':hover ';
      }

      $sprite->setTargetCSS(implode(', ', $tcss));

      foreach ($scales as $scale_key => $scale) {
        $path = $this->getPath($prefix.$scale_key.'/'.$icon.'.png');
        $sprite->setSourceFile($path, $scale);
      }
      $sprites[] = $sprite;
    }

    $sheet = $this->buildSheet($name, true);
    $sheet->setScales($scales);
    foreach ($sprites as $sprite) {
      $sheet->addSprite($sprite);
    }

    return $sheet;
  }

  public function buildDocsSheet() {
    $icons = $this->getDirectoryList('docs_1x');
    $scales = array(
      '1x' => 1,
      '2x' => 2,
    );
    $template = id(new PhutilSprite())
      ->setSourceSize(32, 32);

    $sprites = array();
    $prefix = 'docs_';
    foreach ($icons as $icon) {
      $sprite = id(clone $template)
        ->setName($prefix.$icon)
        ->setTargetCSS('.'.$prefix.$icon);

      foreach ($scales as $scale_key => $scale) {
        $path = $this->getPath($prefix.$scale_key.'/'.$icon.'.png');
        $sprite->setSourceFile($path, $scale);
      }
      $sprites[] = $sprite;
    }

    $sheet = $this->buildSheet('docs', true);
    $sheet->setScales($scales);
    foreach ($sprites as $sprite) {
      $sheet->addSprite($sprite);
    }

    return $sheet;
  }

  public function buildGradientSheet() {
    $gradients = $this->getDirectoryList('gradients');

    $template = new PhutilSprite();

    $unusual_heights = array(
      'dark-menu-label' => 25,
      'breadcrumbs'     => 31,
      'menu-hover'      => 28,
      'menu-label'      => 24,
      'menu-selected'   => 28,
    );

    // Reorder the sprites so less-specific rules generate earlier in the sheet.
    // Otherwise we end up with blue "a.black" buttons because the blue rules
    // have the same specificity but appear later.
    $gradients = array_fuse($gradients);
    $gradients = array_select_keys(
      $gradients,
      array(
        'blue-dark',
        'blue-light',
      )) + $gradients;

    $extra_css = array(
      'black-dark' => ', button.black, a.black, a.black:visited',
      'black-light' => ', button.black:active, a.black:active',
      'blue-dark' => ', button, a.button, a.button:visited, input.inputsubmit',
      'blue-light' => ', button:active, a.button:active',
      'grey-dark' => ', button.grey, input.inputaux, a.grey, a.grey:visited, '.
                        'a.button.disabled, button[disabled], button.disabled',
      'grey-light' => ', button.grey:active, a.grey:active, '.
                        'button.grey_active, a.dropdown-open',
      'green-dark' => ', button.green, a.green, a.green:visited',
      'green-light' => ', button.green:active, a.green:active',
      'dark-menu-label' =>
        ', .phabricator-dark-menu .phabricator-menu-item-type-label',
      'menu-label' =>
        ', .phabricator-side-menu .phabricator-menu-item-type-label',
      'menu-hover' =>
        ', .device-desktop .phabricator-side-menu '.
        'a.phabricator-menu-item-type-link:hover, '.
        '.phabricator-filetree a.phabricator-filetree-item:hover',
      'menu-selected' =>
        ', .phabricator-side-menu .phabricator-menu-item-selected, '.
        '.device-desktop .phabricator-side-menu '.
        'a.phabricator-menu-item-selected:hover, '.
        '.phabricator-nav-local a.phabricator-active-nav-focus',
    );

    $sprites = array();
    foreach ($gradients as $gradient) {
      $path = $this->getPath('gradients/'.$gradient.'.png');
      $sprite = id(clone $template)
        ->setName('gradient-'.$gradient)
        ->setSourceFile($path)
        ->setTargetCSS('.gradient-'.$gradient.idx($extra_css, $gradient));

      $sprite->setSourceSize(4, idx($unusual_heights, $gradient, 26));

      $sprites[] = $sprite;
    }

    $sheet = $this->buildSheet(
      'gradient',
      false,
      PhutilSpriteSheet::TYPE_REPEAT_X,
      ', button, a.button, a.button:visited, input.inputsubmit, '.
      '.phabricator-dark-menu .phabricator-menu-item-type-label, '.
      '.phabricator-side-menu .phabricator-menu-item-type-label, '.
      '.device-desktop .phabricator-side-menu '.
        'a.phabricator-menu-item-type-link:hover, '.
      '.phabricator-side-menu .phabricator-menu-item-selected, '.
      '.device-desktop .phabricator-side-menu '.
        'a.phabricator-menu-item-selected:hover, '.
      '.phabricator-filetree a.phabricator-filetree-item:hover, '.
      '.phabricator-filetree a.phabricator-active-nav-focus');
    foreach ($sprites as $sprite) {
      $sheet->addSprite($sprite);
    }

    return $sheet;
  }

  public function buildAppsSheet() {
    return $this->buildAppsSheetVariant(1);
  }

  public function buildAppsLargeSheet() {
    return $this->buildAppsSheetVariant(2);
  }

  public function buildAppsXLargeSheet() {
    return $this->buildAppsSheetVariant(3);
  }

  private function buildAppsSheetVariant($variant) {

    if ($variant == 1) {
      $scales = array(
        '1x' => 1,
        '2x' => 2,
      );
      $variant_name = 'apps';
      $variant_short = '';
      $size_x = 14;
      $size_y = 14;

      $colors = array(
        'dark'  => 'dark',
      );
    } else if ($variant == 2) {
      $scales = array(
        '2x' => 1,
        '4x' => 2,
      );
      $variant_name = 'apps-large';
      $variant_short = '-large';
      $size_x = 28;
      $size_y = 28;

      $colors = array(
        'light' => 'lb',
        'dark'  => 'dark',
        'blue'  => 'blue',
        'glow'  => 'glow',
      );
    } else {
      $scales = array(
        '4x' => 1,
      );
      $variant_name = 'apps-xlarge';
      $variant_short = '-xlarge';
      $size_x = 56;
      $size_y = 56;

      $colors = array(
        'dark'  => 'dark',
        /*

        TODO: These are available but not currently used.

        'blue'  => 'blue',
        'light' => 'lb',
        'glow'  => 'glow',
        */
      );
    }


    $apps = $this->getDirectoryList('apps_dark_1x');

    $template = id(new PhutilSprite())
      ->setSourceSize($size_x, $size_y);

    $sprites = array();
    foreach ($apps as $app) {
      foreach ($colors as $color => $color_path) {

        $css = '.app-'.$app.'-'.$color.$variant_short;
        if ($color == 'blue' && $variant_name == 'apps-large') {
          $css .= ', .phabricator-crumb-view:hover .app-'.$app.'-dark-large';
        }
        if ($color == 'glow' && $variant_name == 'apps-large') {
          $css .= ', .device-desktop .phabricator-dark-menu a:hover '.
                  '.app-'.$app.'-light-large';
        }

        $sprite = id(clone $template)
          ->setName('app-'.$app.'-'.$color.$variant_short)
          ->setTargetCSS($css);

        foreach ($scales as $scale_name => $scale) {
          $path = $this->getPath(
            'apps_'.$color_path.'_'.$scale_name.'/'.$app.'.png');
          $sprite->setSourceFile($path, $scale);
        }

        $sprites[] = $sprite;
      }
    }

    $sheet = $this->buildSheet($variant_name, count($scales) > 1);
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
only screen and (-webkit-min-device-pixel-ratio: 1.5) {
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


