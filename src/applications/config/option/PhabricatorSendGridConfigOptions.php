<?php

final class PhabricatorSendGridConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Integration with SendGrid');
  }

  public function getDescription() {
    return pht('Configure SendGrid integration.');
  }

  public function getFontIcon() {
    return 'fa-send-o';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('sendgrid.api-user', 'string', null)
        ->setLocked(true)
        ->setDescription(pht('SendGrid API username.')),
      $this->newOption('sendgrid.api-key', 'string', null)
        ->setHidden(true)
        ->setDescription(pht('SendGrid API key.')),
    );
  }

}
