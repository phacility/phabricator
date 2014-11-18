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

      switch ($binary) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $minimum_version = null;
          $bad_versions = array();
          list($err, $stdout, $stderr) = exec_manual('git --version');
          $version = trim(substr($stdout, strlen('git version ')));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $minimum_version = null;
          $bad_versions = array(
            '1.7.1' => pht('This version of Subversion has a bug where '.
                           '"svn diff -c N" does not work for files added '.
                           'in rN (Subverison issue #2873), fixed in 1.7.2.'),);
          list($err, $stdout, $stderr) = exec_manual('svn --version --quiet');
          $version = trim($stdout);
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $minimum_version = '1.9';
          $bad_versions = array(
            '2.1' => pht('This version of Mercurial returns a bad exit code '.
                         'after a successful pull.'),
            '2.2' => pht('This version of Mercurial has a significant memory '.
                         'leak, fixed in 2.2.1. Pushing fails with this '.
                         'version as well; see T3046#54922.'),);
          list($err, $stdout, $stderr) = exec_manual('hg --version --quiet');
          $version = rtrim(
            substr($stdout, strlen('Mercurial Distributed SCM (version ')),
            ")\n");
          break;
      }

      if ($minimum_version &&
        version_compare($version, $minimum_version, '<')) {
        $this->raiseMinimumVersionWarning(
          $binary,
          $minimum_version,
          $version);
      }

      foreach ($bad_versions as $bad_version => $details) {
        if ($bad_version === $version) {
          $this->raiseBadVersionWarning(
            $binary,
            $bad_version);
        }
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

  private function raiseMinimumVersionWarning(
    $binary,
    $minimum_version,
    $version) {

    switch ($binary) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $summary = pht(
          "The '%s' binary is version %s and Phabricator requires version ".
          "%s or higher.",
          $binary,
          $version,
          $minimum_version);
        $message = pht(
          "Please upgrade the '%s' binary to a more modern version.",
          $binary);
        $this->newIssue('bin.'.$binary)
          ->setShortName(pht("Unsupported '%s' Version", $binary))
          ->setName(pht("Unsupported '%s' Version", $binary))
          ->setSummary($summary)
          ->setMessage($summary.' '.$message);
        break;
      }


  }

  private function raiseBadVersionWarning($binary, $bad_version) {

    switch ($binary) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $summary = pht(
          "The '%s' binary is version %s which has bugs that break ".
          "Phabricator.",
          $binary,
          $bad_version);
        $message = pht(
          "Please upgrade the '%s' binary to a more modern version.",
          $binary);
        $this->newIssue('bin.'.$binary)
          ->setShortName(pht("Unsupported '%s' Version", $binary))
          ->setName(pht("Unsupported '%s' Version", $binary))
          ->setSummary($summary)
          ->setMessage($summary.' '.$message);
        break;
      }


  }

}
