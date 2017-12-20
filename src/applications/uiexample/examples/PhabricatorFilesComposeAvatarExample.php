<?php

final class PhabricatorFilesComposeAvatarExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Avatars');
  }

  public function getDescription() {
    return pht('Tests various color palettes and sizes.');
  }

  public function getCategory() {
    return pht('Technical');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $colors = PhabricatorFilesComposeAvatarBuiltinFile::getColorMap();
    $packs = PhabricatorFilesComposeAvatarBuiltinFile::getImagePackMap();
    $builtins = PhabricatorFilesComposeAvatarBuiltinFile::getImageMap();
    $borders = PhabricatorFilesComposeAvatarBuiltinFile::getBorderMap();

    $images = array();
    foreach ($builtins as $builtin => $raw_file) {
      $file = PhabricatorFile::loadBuiltin($viewer, $builtin);
      $images[] = $file->getBestURI();
    }

    $content = array();
    shuffle($colors);
    foreach ($colors as $color) {
      shuffle($borders);
      $color_const = hexdec(trim($color, '#'));
      $border = head($borders);
      $border_color = implode(', ', $border);

      $styles = array();
      $styles[] = 'background-color: '.$color.';';
      $styles[] = 'display: inline-block;';
      $styles[] = 'height: 42px;';
      $styles[] = 'width: 42px;';
      $styles[] = 'border-radius: 3px;';
      $styles[] = 'border: 4px solid rgba('.$border_color.');';

      shuffle($images);
      $png = head($images);

      $image = phutil_tag(
        'img',
        array(
          'src' => $png,
          'height' => 42,
          'width' => 42,
        ));

      $tag = phutil_tag(
        'div',
        array(
          'style' => implode(' ', $styles),
        ),
        $image);

      $content[] = phutil_tag(
        'div',
        array(
          'class' => 'mlr mlb',
          'style' => 'float: left;',
        ),
        $tag);
    }

    $count = new PhutilNumber(
      count($colors) * count($builtins) * count($borders));

    $infoview = id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
      ->appendChild(pht('This installation can generate %s unique '.
      'avatars. You can add additional image packs in '.
      'resources/builtins/alphanumeric/.', $count));

    $info = phutil_tag_div('pmb', $infoview);
    $view = phutil_tag_div('ml', $content);

    return phutil_tag(
      'div',
        array(),
        array(
          $info,
          $view,
        ));
      }
}
