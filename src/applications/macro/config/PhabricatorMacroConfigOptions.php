<?php

final class PhabricatorMacroConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Macro');
  }

  public function getDescription() {
    return pht('Configure Macro.');
  }

  public function getFontIcon() {
    return 'fa-file-image-o';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    return array(
      $this->newOption('metamta.macro.subject-prefix', 'string', '[Macro]')
        ->setDescription(pht('Subject prefix for Macro email.')),
    );
  }

}
