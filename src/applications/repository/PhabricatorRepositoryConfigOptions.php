<?php

final class PhabricatorRepositoryConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Repositories');
  }

  public function getDescription() {
    return pht('Configure repositories.');
  }

  public function getOptions() {
    return array(
      $this->newOption('repository.default-local-path', 'string', null)
        ->setSummary(
          pht("Default location to store local copies of repositories."))
        ->setDescription(
          pht(
            "The default location in which to store local copies of ".
            "repositories. Anything stored in this directory will be assumed ".
            "to be under the control of phabricator, which means that ".
            "Phabricator will try to do some maintenance on working copies ".
            "if there are problems (such as a change to the remote origin ".
            "url). This maintenance may include completely removing (and ".
            "recloning) anything in this directory.\n\n".
            "When set to null, this option is ignored (i.e. Phabricator will ".
            "not fully control any working copies).")),
    );
  }

}
