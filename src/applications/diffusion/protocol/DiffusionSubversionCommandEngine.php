<?php

final class DiffusionSubversionCommandEngine
  extends DiffusionCommandEngine {

  protected function canBuildForRepository(
    PhabricatorRepository $repository) {
    return $repository->isSVN();
  }

  protected function newFormattedCommand($pattern, array $argv) {
    $flags = array();
    $args = array();

    $flags[] = '--non-interactive';

    if ($this->isAnyHTTPProtocol() || $this->isSVNProtocol()) {
      $flags[] = '--no-auth-cache';

      if ($this->isAnyHTTPProtocol()) {
        $flags[] = '--trust-server-cert';
      }

      $credential_phid = $this->getCredentialPHID();
      if ($credential_phid) {
        $key = PassphrasePasswordKey::loadFromPHID(
          $credential_phid,
          PhabricatorUser::getOmnipotentUser());

        $flags[] = '--username %P';
        $args[] = $key->getUsernameEnvelope();

        $flags[] = '--password %P';
        $args[] = $key->getPasswordEnvelope();
      }
    }

    $flags = implode(' ', $flags);
    $pattern = "svn {$flags} {$pattern}";

    return array($pattern, array_merge($args, $argv));
  }

  protected function newCustomEnvironment() {
    $env = array();

    $env['SVN_SSH'] = $this->getSSHWrapper();

    return $env;
  }

}
