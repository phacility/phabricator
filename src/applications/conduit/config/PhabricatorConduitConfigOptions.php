<?php

final class PhabricatorConduitConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Conduit");
  }

  public function getDescription() {
    return pht("Configure conduit.");
  }

  public function getOptions() {
    return array(
      $this->newOption("conduit.servers", "list<string>", array())
        ->setLocked(true)
        ->setSummary(pht("Servers that conduit can connect to."))
        ->setDescription(
            pht(
              "Set an array of servers where conduit can connect to. This is ".
              "an advanced option. Don't touch this unless you know what you ".
              "are doing."))
        ->addExample(
          '["http://phabricator2.example.com/", '.
          '"http://phabricator3.example.com/]"',
           pht('Valid Setting')),
    );
  }

}
