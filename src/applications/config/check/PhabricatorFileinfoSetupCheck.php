<?php

final class PhabricatorFileinfoSetupCheck extends PhabricatorSetupCheck {

  protected function executeChecks() {
    if (!extension_loaded('fileinfo')) {
      $message = pht(
        "The 'fileinfo' extension is not installed. Without 'fileinfo', ".
        "support, Phabricator may not be able to determine the MIME types ".
        "of uploaded files.");

      $this->newIssue('extension.fileinfo')
        ->setName(pht("Missing 'fileinfo' Extension"))
        ->setMessage($message);
    }
  }
}
