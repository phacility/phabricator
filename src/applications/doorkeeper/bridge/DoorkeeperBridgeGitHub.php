<?php

abstract class DoorkeeperBridgeGitHub extends DoorkeeperBridge {

  const APPTYPE_GITHUB  = 'github';
  const APPDOMAIN_GITHUB = 'github.com';

  public function canPullRef(DoorkeeperObjectRef $ref) {
    if ($ref->getApplicationType() != self::APPTYPE_GITHUB) {
      return false;
    }

    if ($ref->getApplicationDomain() != self::APPDOMAIN_GITHUB) {
      return false;
    }

    return true;
  }

  protected function getGitHubAccessToken() {
    $context_token = $this->getContextProperty('github.token');
    if ($context_token) {
      return $context_token->openEnvelope();
    }

    // TODO: Do a bunch of work to fetch the viewer's linked account if
    // they have one.

    return $this->didFailOnMissingLink();
  }

  protected function parseGitHubIssueID($id) {
    $matches = null;
    if (!preg_match('(^([^/]+)/([^/]+)#([1-9]\d*)\z)', $id, $matches)) {
      throw new Exception(
        pht(
          'GitHub Issue ID "%s" is not properly formatted. Expected an ID '.
          'in the form "owner/repository#123".',
          $id));
    }

    return array(
      $matches[1],
      $matches[2],
      (int)$matches[3],
    );
  }


}
