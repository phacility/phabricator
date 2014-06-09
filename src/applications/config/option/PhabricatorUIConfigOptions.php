<?php

final class PhabricatorUIConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('User Interface');
  }

  public function getDescription() {
    return pht('Configure the Phabricator UI, including colors.');
  }

  public function getOptions() {
    $manifest = PHUIIconView::getSheetManifest('main-header');

    $options = array();
    foreach (array_keys($manifest) as $sprite_name) {
      $key = substr($sprite_name, strlen('main-header-'));
      $options[$key] = $key;
    }

    return array(
      $this->newOption('ui.header-color', 'enum', 'dark')
        ->setDescription(
          pht(
            'Sets the color of the main header.'))
        ->setEnumOptions($options),
    );
  }

}
