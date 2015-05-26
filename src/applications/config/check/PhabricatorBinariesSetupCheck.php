<?php

final class PhabricatorBinariesSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

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
        "Without '%s', Phabricator will not be able to generate or render ".
        "diffs in multiple applications.",
        'diff');
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
          ->setName(pht("Unexpected '%s' Behavior", 'diff'))
          ->setMessage(
            pht(
              "The '%s' binary on this system has unexpected behavior: ".
              "it was expected to exit without an error code when passed ".
              "identical files, but exited with code %d.",
              'diff',
              $err));
      }

      list($err) = exec_manual('diff %s %s', $tmp_a, $tmp_c);
      if (!$err) {
        $this->newIssue('bin.diff.diff')
          ->setName(pht("Unexpected 'diff' Behavior"))
          ->setMessage(
            pht(
              "The '%s' binary on this system has unexpected behavior: ".
              "it was expected to exit with a nonzero error code when passed ".
              "differing files, but did not.",
              'diff'));
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

      $version = null;
      switch ($binary) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $minimum_version = null;
          $bad_versions = array();
          list($err, $stdout, $stderr) = exec_manual('git --version');
          $version = trim(substr($stdout, strlen('git version ')));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $minimum_version = '1.5';
          $bad_versions = array(
            '1.7.1' => pht(
              'This version of Subversion has a bug where `%s` does not work '.
              'for files added in rN (Subversion issue #2873), fixed in 1.7.2.',
              'svn diff -c N'),
          );
          list($err, $stdout, $stderr) = exec_manual('svn --version --quiet');
          $version = trim($stdout);
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $minimum_version = '1.9';
          $bad_versions = array(
            '2.1' => pht(
              'This version of Mercurial returns a bad exit code '.
              'after a successful pull.'),
            '2.2' => pht(
              'This version of Mercurial has a significant memory leak, fixed '.
              'in 2.2.1. Pushing fails with this version as well; see %s.',
              'T3046#54922'),
          );
          $version = PhabricatorRepositoryVersion::getMercurialVersion();
          break;
      }

      if ($version === null) {
        $this->raiseUnknownVersionWarning($binary);
      } else {
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

  private function raiseUnknownVersionWarning($binary) {
    $summary = pht(
      'Unable to determine the version number of "%s".',
      $binary);

    $message = pht(
      'Unable to determine the version number of "%s". Usually, this means '.
      'the program changed its version format string recently and Phabricator '.
      'does not know how to parse the new one yet, but might indicate that '.
      'you have a very old (or broken) binary.'.
      "\n\n".
      'Because we can not determine the version number, checks against '.
      'minimum and known-bad versions will be skipped, so we might fail '.
      'to detect an incompatible binary.'.
      "\n\n".
      'You may be able to resolve this issue by updating Phabricator, since '.
      'a newer version of Phabricator is likely to be able to parse the '.
      'newer version string.'.
      "\n\n".
      'If updating Phabricator does not fix this, you can report the issue '.
      'to the upstream so we can adjust the parser.'.
      "\n\n".
      'If you are confident you have a recent version of "%s" installed and '.
      'working correctly, it is usually safe to ignore this warning.',
      $binary,
      $binary);

    $this->newIssue('bin.'.$binary.'.unknown-version')
      ->setShortName(pht("Unknown '%s' Version", $binary))
      ->setName(pht("Unknown '%s' Version", $binary))
      ->setSummary($summary)
      ->setMessage($message)
      ->addLink(
        PhabricatorEnv::getDoclink('Contributing Bug Reports'),
        pht('Report this Issue to the Upstream'));
  }

  private function raiseMinimumVersionWarning(
    $binary,
    $minimum_version,
    $version) {

    switch ($binary) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
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
