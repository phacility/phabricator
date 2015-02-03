<?php

final class PhabricatorMailgunConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Integration with Mailgun');
  }

  public function getDescription() {
    return pht('Configure Mailgun integration.');
  }

  public function getFontIcon() {
    return 'fa-send-o';
  }

  public function getOptions() {
    return array(
      $this->newOption('mailgun.domain', 'string', null)
        ->setLocked(true)
        ->setDescription(
          pht(
            'Mailgun domain name. See https://mailgun.com/cp/domains'))
        ->addExample('mycompany.com', 'Use specific domain'),
      $this->newOption('mailgun.api-key', 'string', null)
        ->setMasked(true)
        ->setDescription(pht('Mailgun API key.')),
    );

  }

}
