<?php

final class PhabricatorMacroConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Macro");
  }

  public function getDescription() {
    return pht("Configure Macro.");
  }

  public function getOptions() {
    return array(
      $this->newOption('metamta.macro.reply-handler-domain', 'string', null)
        ->setDescription(pht(
          'As {{metamta.maniphest.reply-handler-domain}}, but affects Macro.')),
      $this->newOption('metamta.macro.subject-prefix', 'string', '[Macro]')
        ->setDescription(pht('Subject prefix for Macro email.'))
    );
  }

}
