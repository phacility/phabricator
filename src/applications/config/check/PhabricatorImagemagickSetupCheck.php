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
          "You have enabled Imagemagick in your config, but the '%s' ".
          "binary is not in the webserver's %s. Disable imagemagick ".
          "or make it available to the webserver.",
          'convert',
          '$PATH');

        $this->newIssue('files.enable-imagemagick')
        ->setName(pht(
          "'%s' binary not found or Imagemagick is not installed.", 'convert'))
        ->setMessage($message)
        ->addRelatedPhabricatorConfig('files.enable-imagemagick')
        ->addPhabricatorConfig('environment.append-paths');
      }
    }
  }
}
