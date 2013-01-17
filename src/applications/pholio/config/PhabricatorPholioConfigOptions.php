<?php

final class PhabricatorPholioConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Pholio");
  }

  public function getDescription() {
    return pht("Configure Pholio.");
  }

  public function getOptions() {
    return array(
      $this->newOption('metamta.pholio.reply-handler-domain', 'string', null)
        ->setDescription(
          pht(
            'Like {{metamta.maniphest.reply-handler-domain}}, but affects '.
            'Pholio.')),
      $this->newOption('metamta.pholio.subject-prefix', 'string', '[Pholio]')
        ->setDescription(pht('Subject prefix for Pholio email.'))
    );
  }

}
