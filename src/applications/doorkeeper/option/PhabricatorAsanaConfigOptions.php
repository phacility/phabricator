<?php

final class PhabricatorAsanaConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Integration with Asana");
  }

  public function getDescription() {
    return pht("Asana integration options.");
  }

  public function getOptions() {
    return array(
      $this->newOption('asana.workspace-id', 'string', null)
        ->setSummary(pht("Workspace ID to publish into.")),
    );
  }

}
