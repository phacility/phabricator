<?php

final class PhabricatorLandoConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Lando');
  }

  public function getDescription() {
    return pht('Configure Lando Settings.');
  }

  public function getIcon() {
    return 'fa-cog';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'lando-ui.url',
        'string',
        '')
        ->setDescription(pht('Full URL for the Lando UI server. '.
                             'Set to an empty string to disable the link on the Revision page.'))
    );
  }
}
