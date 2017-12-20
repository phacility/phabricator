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

    $env['HOME'] = PhabricatorEnv::getEmptyCWD();

    $env['GIT_SSH'] = $this->getSSHWrapper();

    if ($this->isAnyHTTPProtocol()) {
      $uri = $this->getURI();
      if ($uri) {
        $proxy = PhutilHTTPEngineExtension::buildHTTPProxyURI($uri);
        if ($proxy) {
          if ($this->isHTTPSProtocol()) {
            $env_key = 'https_proxy';
          } else {
            $env_key = 'http_proxy';
          }
          $env[$env_key] = (string)$proxy;
        }
      }
    }

    return $env;
  }

}
