<?php

final class PhabricatorOwnersConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Owners");
  }

  public function getDescription() {
    return pht("Configure Owners.");
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'metamta.package.reply-handler',
        'class',
        'OwnersPackageReplyHandler')
        ->setBaseClass('PhabricatorMailReplyHandler')
        ->setDescription(pht('Reply handler for owners mail.')),
      $this->newOption('metamta.package.subject-prefix', 'string', '[Package]')
        ->setDescription(pht('Subject prefix for Owners email.'))
    );
  }

}
