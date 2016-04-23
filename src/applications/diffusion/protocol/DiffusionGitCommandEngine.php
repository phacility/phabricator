<?php

final class DiffusionGitCommandEngine
  extends DiffusionCommandEngine {

  protected function canBuildForRepository(
    PhabricatorRepository $repository) {
    return $repository->isGit();
  }

  protected function newFormattedCommand($pattern, array $argv) {
    $pattern = "git {$pattern}";
    return array($pattern, $argv);
  }

  protected function newCustomEnvironment() {
    $env = array();

    // NOTE: See T2965. Some time after Git 1.7.5.4, Git started fataling if
    // it can not read $HOME. For many users, $HOME points at /root (this
    // seems to be a default result of Apache setup). Instead, explicitly
    // point $HOME at a readable, empty directory so that Git looks for the
    // config file it's after, fails to locate it, and moves on. This is
    // really silly, but seems like the least damaging approach to
    // mitigating the issue.

    $root = dirname(phutil_get_library_root('phabricator'));
    $env['HOME'] = $root.'/support/empty/';

    if ($this->isAnySSHProtocol()) {
      $env['GIT_SSH'] = $this->getSSHWrapper();
    }

    return $env;
  }

}
