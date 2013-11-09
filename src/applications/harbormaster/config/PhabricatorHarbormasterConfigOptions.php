<?php

final class PhabricatorHarbormasterConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Harbormaster');
  }

  public function getDescription() {
    return pht('Configure Harbormaster build engine.');
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'harbormaster.temporary.hosts.whitelist',
        'list<string>',
        array())
        ->setSummary('Temporary configuration value.')
        ->setLocked(true)
        ->setDescription(
          pht(
            "This specifies a whitelist of remote hosts that the \"Run ".
            "Remote Command\" may connect to.  This is a temporary ".
            "configuration option as Drydock is not yet available.".
            "\n\n".
            "**This configuration option will be removed in the future and ".
            "your build configuration will no longer work when Drydock ".
            "replaces this option.  There is ABSOLUTELY NO SUPPORT for ".
            "using this functionality!**"))
    );
  }

}
