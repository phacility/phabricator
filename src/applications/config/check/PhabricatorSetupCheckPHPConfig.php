<?php

final class PhabricatorSetupCheckPHPConfig extends PhabricatorSetupCheck {

  public function getExecutionOrder() {
    return 0;
  }

  protected function executeChecks() {
    $safe_mode = ini_get('safe_mode');
    if ($safe_mode) {
      $message = pht(
        "You have 'safe_mode' enabled in your PHP configuration, but ".
        "Phabricator will not run in safe mode. Safe mode has been deprecated ".
        "in PHP 5.3 and removed in PHP 5.4.".
        "\n\n".
        "Disable safe mode to continue.");

      $this->newIssue('php.safe_mode')
        ->setIsFatal(true)
        ->setName(pht('Disable PHP safe_mode'))
        ->setMessage($message)
        ->addPHPConfig('safe_mode');
      return;
    }

    $open_basedir = ini_get('open_basedir');
    if ($open_basedir) {

      // 'open_basedir' restricts which files we're allowed to access with
      // file operations. This might be okay -- we don't need to write to
      // arbitrary places in the filesystem -- but we need to access certain
      // resources. This setting is unlikely to be providing any real measure
      // of security so warn even if things look OK.

      $failures = array();

      try {
        $open_libphutil = class_exists('Future');
      } catch (Exception $ex) {
        $failures[] = $ex->getMessage();
      }

      try {
        $open_arcanist = class_exists('ArcanistDiffParser');
      } catch (Exception $ex) {
        $failures[] = $ex->getMessage();
      }

      $open_urandom = false;
      try {
        Filesystem::readRandomBytes(1);
        $open_urandom = true;
      } catch (FilesystemException $ex) {
        $failures[] = $ex->getMessage();
      }

      try {
        $tmp = new TempFile();
        file_put_contents($tmp, '.');
        $open_tmp = @fopen((string)$tmp, 'r');
        if (!$open_tmp) {
          $failures[] = pht(
            "Unable to read temporary file '%s'.",
            (string)$tmp);
        }
      } catch (Exception $ex) {
        $message = $ex->getMessage();
        $dir = sys_get_temp_dir();
        $failures[] = pht(
          "Unable to open temp files from '%s': %s",
          $dir,
          $message);
      }

      $issue = $this->newIssue('php.open_basedir')
        ->setName(pht('Disable PHP open_basedir'))
        ->addPHPConfig('open_basedir');

      if ($failures) {
        $message = pht(
          "Your server is configured with 'open_basedir', which prevents ".
          "Phabricator from opening files it requires access to.".
          "\n\n".
          "Disable this setting to continue.".
          "\n\n".
          "Failures:\n\n%s",
          implode("\n\n", $failures));

        $issue
          ->setIsFatal(true)
          ->setMessage($message);

        return;
      } else {
        $summary = pht(
          "You have 'open_basedir' configured in your PHP settings, which ".
          "may cause some features to fail.");

        $message = pht(
          "You have 'open_basedir' configured in your PHP settings. Although ".
          "this setting appears permissive enough that Phabricator will ".
          "work properly, you may still run into problems because of it.".
          "\n\n".
          "Consider disabling 'open_basedir'.");

        $issue
          ->setSummary($summary)
          ->setMessage($message);
      }
    }
  }
}
