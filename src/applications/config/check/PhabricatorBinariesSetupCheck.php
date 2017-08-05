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
        continue;
      }

      $version = PhutilBinaryAnalyzer::getForBinary($binary)
        ->getBinaryVersion();

      switch ($vcs['versionControlSystem']) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $bad_versions = array();
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $bad_versions = array(
            // We need 1.5 for "--depth", see T7228.
            '< 1.5' => pht(
              'The minimum supported version of Subversion is 1.5, which '.
              'was released in 2008.'),
            '= 1.7.1' => pht(
              'This version of Subversion has a bug where `%s` does not work '.
              'for files added in rN (Subversion issue #2873), fixed in 1.7.2.',
              'svn diff -c N'),
          );
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $bad_versions = array(
            // We need 1.9 for HTTP cloning, see T3046.
            '< 1.9' => pht(
              'The minimum supported version of Mercurial is 1.9, which was '.
              'released in 2011.'),
            '= 2.1' => pht(
              'This version of Mercurial returns a bad exit code '.
              'after a successful pull.'),
            '= 2.2' => pht(
              'This version of Mercurial has a significant memory leak, fixed '.
              'in 2.2.1. Pushing fails with this version as well; see %s.',
              'T3046#54922'),
          );
          break;
      }

      if ($version === null) {
        $this->raiseUnknownVersionWarning($binary);
      } else {
        $version_details = array();

        foreach ($bad_versions as $spec => $details) {
          list($operator, $bad_version) = explode(' ', $spec, 2);
          $is_bad = version_compare($version, $bad_version, $operator);
          if ($is_bad) {
            $version_details[] = pht(
              '(%s%s) %s',
              $operator,
              $bad_version,
              $details);
          }
        }

        if ($version_details) {
          $this->raiseBadVersionWarning(
            $binary,
            $version,
            $version_details);
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

  private function raiseBadVersionWarning($binary, $version, array $problems) {
    $summary = pht(
      'This server has a known bad version of "%s".',
      $binary);

    $message = array();

    $message[] = pht(
      'This server has a known bad version of "%s" installed ("%s"). This '.
      'version is not supported, or contains important bugs or security '.
      'vulnerabilities which are fixed in a newer version.',
      $binary,
      $version);

    $message[] = pht('You should upgrade this software.');

    $message[] = pht('The known issues with this old version are:');

    foreach ($problems as $problem) {
      $message[] = $problem;
    }

    $message = implode("\n\n", $message);

    $this->newIssue("bin.{$binary}.bad-version")
      ->setName(pht('Unsupported/Insecure "%s" Version', $binary))
      ->setSummary($summary)
      ->setMessage($message);
  }

}
