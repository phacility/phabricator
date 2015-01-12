<?php

final class PhabricatorFlagSelectControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'phabricator-flag-select-control';
  }

  protected function renderInput() {
    require_celerity_resource('phabricator-flag-css');

    $colors = PhabricatorFlagColor::getColorNameMap();

    $value_map = array_fuse($this->getValue());

    $file_map = array(
      PhabricatorFlagColor::COLOR_RED => 'red',
      PhabricatorFlagColor::COLOR_ORANGE => 'orange',
      PhabricatorFlagColor::COLOR_YELLOW => 'yellow',
      PhabricatorFlagColor::COLOR_GREEN => 'green',
      PhabricatorFlagColor::COLOR_BLUE => 'blue',
      PhabricatorFlagColor::COLOR_PINK => 'pink',
      PhabricatorFlagColor::COLOR_PURPLE => 'purple',
      PhabricatorFlagColor::COLOR_CHECKERED => 'finish',
    );

    $out = array();
    foreach ($colors as $const => $name) {
      // TODO: This should probably be a sprite sheet.
      $partial = $file_map[$const];
      $uri = '/rsrc/image/icon/fatcow/flag_'.$partial.'.png';
      $uri = celerity_get_resource_uri($uri);

      $icon = id(new PHUIIconView())
        ->setImage($uri);

      $input = phutil_tag(
        'input',
        array(
          'type' => 'checkbox',
          'name' => $this->getName().'[]',
          'value' => $const,
          'checked' => isset($value_map[$const])
            ? 'checked'
            : null,
          'class' => 'phabricator-flag-select-checkbox',
        ));

      $label = phutil_tag(
        'label',
        array(
          'class' => 'phabricator-flag-select-label',
        ),
        array(
          $icon,
          $input,
        ));

      $out[] = $label;
    }

    return $out;
  }

}
