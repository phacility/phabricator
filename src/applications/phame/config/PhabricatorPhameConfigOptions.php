<?php

final class PhabricatorPhameConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Phame");
  }

  public function getDescription() {
    return pht("Configure Phame blogs.");
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'phame.skins',
        'list<string>',
        array(
          'externals/skins/',
        ))
        ->setDescription(
          pht('List of directories where Phame will look for skins.')),
    );
  }

}
