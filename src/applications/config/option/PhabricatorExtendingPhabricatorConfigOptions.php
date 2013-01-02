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
      $this->newOption('load-libraries', 'list<string>', null)
        ->setSummary(pht("Paths to additional phutil libraries to load."))
        ->addExample('/srv/our-sekrit-libs/sekrit-phutil', 'Valid Setting'),
      $this->newOption('events.listeners', 'list<string>', null)
        ->setSummary(
          pht("Listeners receive callbacks when interesting things occur."))
        ->setDescription(
          pht(
            "You can respond to various application events by installing ".
            "listeners, which will receive callbacks when interesting things ".
            "occur. Specify a list of classes which extend ".
            "PhabricatorEventListener here."))
        ->addExample('MyEventListener', 'Valid Setting'),
      $this->newOption(
        'celerity.resource-path',
        'string',
        '__celerity_resource_map__.php')
        ->setSummary(
          pht("Custom celerity resource map."))
        ->setDescription(
          pht(
            "Path to custom celerity resource map relative to ".
            "'phabricator/src'. See also `scripts/celerity_mapper.php`."))
        ->addExample('local/my_celerity_map.php', 'Valid Setting'),
    );
  }

}
