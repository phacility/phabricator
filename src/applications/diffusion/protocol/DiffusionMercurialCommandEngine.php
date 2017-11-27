<?php

final class DiffusionMercurialCommandEngine
  extends DiffusionCommandEngine {

  protected function canBuildForRepository(
    PhabricatorRepository $repository) {
    return $repository->isHg();
  }

  protected function newFormattedCommand($pattern, array $argv) {
    $args = array();

    // Crudely blacklist commands which look like they may contain command
    // injection via "--config" or "--debugger". See T13012. To do this, we
    // print the whole command, parse it using shell rules, then examine each
    // argument to see if it looks like "--config" or "--debugger".

    $test_command = call_user_func_array(
      'csprintf',
      array_merge(array($pattern), $argv));
    $test_args = id(new PhutilShellLexer())
      ->splitArguments($test_command);

    foreach ($test_args as $test_arg) {
      if (preg_match('/^--(config|debugger)/i', $test_arg)) {
        throw new DiffusionMercurialFlagInjectionException(
          pht(
            'Mercurial command appears to contain unsafe injected "--config" '.
            'or "--debugger": %s',
            $test_command));
      }
    }

    // NOTE: Here, and in Git and Subversion, we override the SSH command even
    // if the repository does not use an SSH remote, since our SSH wrapper
    // defuses an attack against older versions of Mercurial, Git and
    // Subversion (see T12961) and it's possible to execute this attack
    // in indirect ways, like by using an SSH subrepo inside an HTTP repo.

    $pattern = "hg --config ui.ssh=%s {$pattern}";
    $args[] = $this->getSSHWrapper();

    return array($pattern, array_merge($args, $argv));
  }

  protected function newCustomEnvironment() {
    $env = array();

    // NOTE: This overrides certain configuration, extensions, and settings
    // which make Mercurial commands do random unusual things.
    $env['HGPLAIN'] = 1;

    return $env;
  }

  /**
   * Sanitize output of an `hg` command invoked with the `--debug` flag to make
   * it usable.
   *
   * @param string Output from `hg --debug ...`
   * @return string Usable output.
   */
  public static function filterMercurialDebugOutput($stdout) {
    // When hg commands are run with `--debug` and some config file isn't
    // trusted, Mercurial prints out a warning to stdout, twice, after Feb 2011.
    //
    // http://selenic.com/pipermail/mercurial-devel/2011-February/028541.html
    //
    // After Jan 2015, it may also fail to write to a revision branch cache.

    $ignore = array(
      'ignoring untrusted configuration option',
      "couldn't write revision branch cache:",
    );

    foreach ($ignore as $key => $pattern) {
      $ignore[$key] = preg_quote($pattern, '/');
    }

    $ignore = '('.implode('|', $ignore).')';

    $lines = preg_split('/(?<=\n)/', $stdout);
    $regex = '/'.$ignore.'.*\n$/';

    foreach ($lines as $key => $line) {
      $lines[$key] = preg_replace($regex, '', $line);
    }

    return implode('', $lines);
  }

}
