<?php

final class PhabricatorFilesComposeAvatarExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Generate Avatar Images');
  }

  public function getDescription() {
    return pht('Tests various color palettes and sizes.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $colors = PhabricatorFilesComposeAvatarBuiltinFile::getColorMap();
    $builtins = PhabricatorFilesComposeAvatarBuiltinFile::getImageMap();
    $borders = PhabricatorFilesComposeAvatarBuiltinFile::getBorderMap();

    shuffle($colors);
    $images = array();
    foreach ($builtins as $builtin => $raw_file) {
      $file = PhabricatorFile::loadBuiltin($viewer, $builtin);
      $images[] = $file->getBestURI();
    }

    $content = array();
    foreach ($colors as $color) {
      shuffle($borders);
      $border = head($borders);

      $styles = array();
      $styles[] = 'background-color: '.$color.';';
      $styles[] = 'display: inline-block;';
      $styles[] = 'height: 46px;';
      $styles[] = 'width: 46px;';
      $styles[] = 'border-radius: 3px;';
      $styles[] = 'border: 4px solid '.$border.';';

      shuffle($images);
      $png = head($images);

      $image = phutil_tag(
        'img',
        array(
          'src' => $png,
          'height' => 46,
          'width' => 46,
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

    $view = phutil_tag_div('ml', $content);

    return phutil_tag(
      'div',
        array(),
        array(
          $view,
        ));
      }
}
