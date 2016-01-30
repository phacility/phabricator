<?php

final class PhabricatorMailgunConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Integration with Mailgun');
  }

  public function getDescription() {
    return pht('Configure Mailgun integration.');
  }

  public function getIcon() {
    return 'fa-send-o';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('mailgun.domain', 'string', null)
        ->setLocked(true)
        ->setDescription(
          pht(
            'Mailgun domain name. See %s.',
            'https://mailgun.com/cp/domains'))
        ->addExample('mycompany.com', pht('Use specific domain')),
      $this->newOption('mailgun.api-key', 'string', null)
        ->setHidden(true)
        ->setDescription(pht('Mailgun API key.')),
    );

  }

}
