<?php

final class PhabricatorPathSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    // NOTE: We've already appended `environment.append-paths`, so we don't
    // need to explicitly check for it.
    $path = getenv('PATH');

    if (!$path) {
      $summary = pht(
        'The environmental variable %s is empty. Phabricator will not '.
        'be able to execute some commands.',
        '$PATH');

      $message = pht(
        "The environmental variable %s is empty. Phabricator needs to execute ".
        "some system commands, like `%s`, `%s`, `%s`, and `%s`. To execute ".
        "these commands, the binaries must be available in the webserver's ".
        "%s. You can set additional paths in Phabricator configuration.",
        '$PATH',
        'svn',
        'git',
        'hg',
        'diff',
        '$PATH');

      $this
        ->newIssue('config.environment.append-paths')
        ->setName(pht('%s Not Set', '$PATH'))
        ->setSummary($summary)
        ->setMessage($message)
        ->addPhabricatorConfig('environment.append-paths');

      // Bail on checks below.
      return;
    }

    // Users are remarkably industrious at misconfiguring software. Try to
    // catch mistaken configuration of PATH.

    $path_parts = explode(PATH_SEPARATOR, $path);
    $bad_paths = array();
    foreach ($path_parts as $path_part) {
      if (!strlen($path_part)) {
        continue;
      }

      $message = null;
      $not_exists = false;
      foreach (Filesystem::walkToRoot($path_part) as $part) {
        if (!Filesystem::pathExists($part)) {
          $not_exists = $part;
          // Walk up so we can tell if this is a readability issue or not.
          continue;
        } else if (!is_dir(Filesystem::resolvePath($part))) {
          $message = pht(
            "The PATH component '%s' (which resolves as the absolute path ".
            "'%s') is not usable because '%s' is not a directory.",
            $path_part,
            Filesystem::resolvePath($path_part),
            $part);
        } else if (!is_readable($part)) {
          $message = pht(
            "The PATH component '%s' (which resolves as the absolute path ".
            "'%s') is not usable because '%s' is not readable.",
            $path_part,
            Filesystem::resolvePath($path_part),
            $part);
        } else if ($not_exists) {
          $message = pht(
            "The PATH component '%s' (which resolves as the absolute path ".
            "'%s') is not usable because '%s' does not exist.",
            $path_part,
            Filesystem::resolvePath($path_part),
            $not_exists);
        } else {
          // Everything seems good.
          break;
        }

        if ($message !== null) {
          break;
        }
      }

      if ($message === null) {
        if (!phutil_is_windows() && !@file_exists($path_part.'/.')) {
          $message = pht(
            "The PATH component '%s' (which resolves as the absolute path ".
            "'%s') is not usable because it is not traversable (its '%s' ".
            "permission bit is not set).",
            $path_part,
            Filesystem::resolvePath($path_part),
            '+x');
        }
      }

      if ($message !== null) {
        $bad_paths[$path_part] = $message;
      }
    }

    if ($bad_paths) {
      foreach ($bad_paths as $path_part => $message) {
        $digest = substr(PhabricatorHash::digest($path_part), 0, 8);

        $this
          ->newIssue('config.PATH.'.$digest)
          ->setName(pht('%s Component Unusable', '$PATH'))
          ->setSummary(
            pht(
              'A component of the configured PATH can not be used by '.
              'the webserver: %s',
              $path_part))
          ->setMessage(
            pht(
              "The configured PATH includes a component which is not usable. ".
              "Phabricator will be unable to find or execute binaries located ".
              "here:".
              "\n\n".
              "%s".
              "\n\n".
              "The user that the webserver runs as must be able to read all ".
              "the directories in PATH in order to make use of them.",
              $message))
          ->addPhabricatorConfig('environment.append-paths');
      }
    }

  }
}
