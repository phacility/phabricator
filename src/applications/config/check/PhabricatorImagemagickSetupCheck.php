<?php

final class PhabricatorImagemagickSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    $imagemagick = PhabricatorEnv::getEnvConfig('files.enable-imagemagick');
    if ($imagemagick) {
      if (!Filesystem::binaryExists('convert')) {
        $message = pht(
          'You have enabled Imagemagick in your config, but the \'convert\' '.
          'binary is not in the webserver\'s $PATH. Disable imagemagick '.
          'or make it available to the webserver.');

        $this->newIssue('files.enable-imagemagick')
        ->setName(pht(
          "'convert' binary not found or Imagemagick is not installed."))
        ->setMessage($message)
        ->addRelatedPhabricatorConfig('files.enable-imagemagick')
        ->addPhabricatorConfig('environment.append-paths');
      }
    }
  }
}
