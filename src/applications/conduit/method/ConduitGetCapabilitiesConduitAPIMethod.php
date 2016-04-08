<?php

final class ConduitGetCapabilitiesConduitAPIMethod extends ConduitAPIMethod {

  public function getAPIMethodName() {
    return 'conduit.getcapabilities';
  }

  public function shouldRequireAuthentication() {
    return false;
  }

  public function getMethodDescription() {
    return pht(
      'List capabilities, wire formats, and authentication protocols '.
      'available on this server.');
  }

  protected function defineParamTypes() {
    return array();
  }

  protected function defineReturnType() {
    return 'dict<string, any>';
  }

  public function getRequiredScope() {
    return self::SCOPE_ALWAYS;
  }

  protected function execute(ConduitAPIRequest $request) {
    $authentication = array(
      'token',
      'asymmetric',
      'session',
      'sessionless',
    );

    $oauth_app = 'PhabricatorOAuthServerApplication';
    if (PhabricatorApplication::isClassInstalled($oauth_app)) {
      $authentication[] = 'oauth';
    }

    return array(
      'authentication' => $authentication,
      'signatures' => array(
        'consign',
      ),
      'input' => array(
        'json',
        'urlencoded',
      ),
      'output' => array(
        'json',
        'human',
      ),
    );
  }

}
