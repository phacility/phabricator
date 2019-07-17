<?php

final class PhabricatorZipSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    if (!extension_loaded('zip')) {
      $message = pht(
        'The PHP "zip" extension is not installed. This extension is '.
        'required by certain data export operations, including exporting '.
        'data to Excel.'.
        "\n\n".
        'To clear this setup issue, install the extension and restart your '.
        'webserver.'.
        "\n\n".
        'You may safely ignore this issue if you do not plan to export '.
        'data in Zip archives or Excel spreadsheets, or intend to install '.
        'the extension later.');

      $this->newIssue('extension.zip')
        ->setName(pht('Missing "zip" Extension'))
        ->setMessage($message)
        ->addPHPExtension('zip');
    }
  }
}
