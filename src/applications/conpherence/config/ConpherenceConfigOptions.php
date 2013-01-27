<?php

final class ConpherenceConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Copherence');
  }

  public function getDescription() {
    return pht('Configure Conpherence messaging.');
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'metamta.conpherence.subject-prefix',
        'string',
        '[Conpherence]')
        ->setDescription(pht('Subject prefix for Conpherence mail.')),
    );
  }

}
