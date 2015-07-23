<?php

final class PhabricatorExtendingPhabricatorConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Extending Phabricator');
  }

  public function getDescription() {
    return pht('Make Phabricator even cooler!');
  }

  public function getFontIcon() {
    return 'fa-rocket';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('load-libraries', 'list<string>', array())
        ->setLocked(true)
        ->setSummary(pht('Paths to additional phutil libraries to load.'))
        ->addExample('/srv/our-libs/sekrit-phutil', pht('Valid Setting')),
      $this->newOption('events.listeners', 'list<string>', array())
        ->setLocked(true)
        ->setSummary(
          pht('Listeners receive callbacks when interesting things occur.'))
        ->setDescription(
          pht(
            'You can respond to various application events by installing '.
            'listeners, which will receive callbacks when interesting things '.
            'occur. Specify a list of classes which extend '.
            'PhabricatorEventListener here.'))
        ->addExample('MyEventListener', pht('Valid Setting')),
       $this->newOption(
         'aphront.default-application-configuration-class',
         'class',
         'AphrontDefaultApplicationConfiguration')
        ->setLocked(true)
        ->setBaseClass('AphrontApplicationConfiguration')
        // TODO: This could probably use some better documentation.
        ->setDescription(pht('Application configuration class.')),
    );
  }

}
