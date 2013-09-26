<?php

final class PhabricatorSetupCheckBinaries extends PhabricatorSetupCheck {


  protected function executeChecks() {

    if (phutil_is_windows()) {
      $bin_name = 'where';
    } else {
      $bin_name = 'which';
    }

    if (!Filesystem::binaryExists($bin_name)) {
      $message = pht(
        "Without '%s', Phabricator can not test for the availability ".
        "of other binaries.",
        $bin_name);
      $this->raiseWarning($bin_name, $message);

      // We need to return here if we can't find the 'which' / 'where' binary
      // because the other tests won't be valid.
      return;
    }

    if (!Filesystem::binaryExists('diff')) {
      $message = pht(
        "Without 'diff', Phabricator will not be able to generate or render ".
        "diffs in multiple applications.");
      $this->raiseWarning('diff', $message);
    } else {
      $tmp_a = new TempFile();
      $tmp_b = new TempFile();
      $tmp_c = new TempFile();

      Filesystem::writeFile($tmp_a, 'A');
      Filesystem::writeFile($tmp_b, 'A');
      Filesystem::writeFile($tmp_c, 'B');

      list($err) = exec_manual('diff %s %s', $tmp_a, $tmp_b);
      if ($err) {
        $this->newIssue('bin.diff.same')
          ->setName(pht("Unexpected 'diff' Behavior"))
          ->setMessage(
            pht(
              "The 'diff' binary on this system has unexpected behavior: ".
              "it was expected to exit without an error code when passed ".
              "identical files, but exited with code %d.",
              $err));
      }

      list($err) = exec_manual('diff %s %s', $tmp_a, $tmp_c);
      if (!$err) {
        $this->newIssue('bin.diff.diff')
          ->setName(pht("Unexpected 'diff' Behavior"))
          ->setMessage(
            pht(
              "The 'diff' binary on this system has unexpected behavior: ".
              "it was expected to exit with a nonzero error code when passed ".
              "differing files, but did not."));
      }
    }

    $table = new PhabricatorRepository();
    $vcses = queryfx_all(
      $table->establishConnection('r'),
      'SELECT DISTINCT versionControlSystem FROM %T',
      $table->getTableName());

    foreach ($vcses as $vcs) {
      switch ($vcs['versionControlSystem']) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $binary = 'git';
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $binary = 'svn';
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $binary = 'hg';
          break;
        default:
          $binary = null;
          break;
      }
      if (!$binary) {
        continue;
      }

      if (!Filesystem::binaryExists($binary)) {
        $message = pht(
          'You have at least one repository configured which uses this '.
          'version control system. It will not work without the VCS binary.');
        $this->raiseWarning($binary, $message);
      }
    }

  }

  private function raiseWarning($bin, $message) {
    if (phutil_is_windows()) {
      $preamble = pht(
        "The '%s' binary could not be found. Set the webserver's %s ".
        "environmental variable to include the directory where it resides, or ".
        "add that directory to '%s' in the Phabricator configuration.",
        $bin,
        'PATH',
        'environment.append-paths');
    } else {
      $preamble = pht(
        "The '%s' binary could not be found. Symlink it into '%s', or set the ".
        "webserver's %s environmental variable to include the directory where ".
        "it resides, or add that directory to '%s' in the Phabricator ".
        "configuration.",
        $bin,
        'phabricator/support/bin/',
        'PATH',
        'environment.append-paths');
    }

    $this->newIssue('bin.'.$bin)
      ->setShortName(pht("'%s' Missing", $bin))
      ->setName(pht("Missing '%s' Binary", $bin))
      ->setSummary(
        pht("The '%s' binary could not be located or executed.", $bin))
      ->setMessage($preamble.' '.$message)
      ->addPhabricatorConfig('environment.append-paths');
  }

}
