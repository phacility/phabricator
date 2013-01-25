<?php

final class PhabricatorExtendingPhabricatorConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Extending Phabricator");
  }

  public function getDescription() {
    return pht("Make Phabricator even cooler!");
  }

  public function getOptions() {
    return array(
      $this->newOption('load-libraries', 'list<string>', array())
        ->setLocked(true)
        ->setSummary(pht("Paths to additional phutil libraries to load."))
        ->addExample('/srv/our-libs/sekrit-phutil', pht('Valid Setting')),
      $this->newOption('events.listeners', 'list<string>', array())
        ->setLocked(true)
        ->setSummary(
          pht("Listeners receive callbacks when interesting things occur."))
        ->setDescription(
          pht(
            "You can respond to various application events by installing ".
            "listeners, which will receive callbacks when interesting things ".
            "occur. Specify a list of classes which extend ".
            "PhabricatorEventListener here."))
        ->addExample('MyEventListener', pht('Valid Setting')),
      $this->newOption(
        'celerity.resource-path',
        'string',
        '__celerity_resource_map__.php')
        ->setLocked(true)
        ->setSummary(
          pht("Custom celerity resource map."))
        ->setDescription(
          pht(
            "Path to custom celerity resource map relative to ".
            "'phabricator/src'. See also `scripts/celerity_mapper.php`."))
        ->addExample('local/my_celerity_map.php', pht('Valid Setting')),
       $this->newOption(
         'aphront.default-application-configuration-class',
         'class',
         'AphrontDefaultApplicationConfiguration')
        ->setBaseClass('AphrontApplicationConfiguration')
        // TODO: This could probably use some better documentation.
        ->setDescription(pht("Application configuration class.")),
       $this->newOption(
         'controller.oauth-registration',
         'class',
         'PhabricatorOAuthDefaultRegistrationController')
        ->setBaseClass('PhabricatorOAuthRegistrationController')
        ->setDescription(pht("OAuth registration controller.")),
    );
  }

}
