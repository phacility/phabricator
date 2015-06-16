<?php

final class PhabricatorPersonaAuthProvider extends PhabricatorAuthProvider {

  private $adapter;

  public function getProviderName() {
    return pht('Persona');
  }

  public function getDescriptionForCreate() {
    return pht('Allow users to login or register using Mozilla Persona.');
  }

  public function getAdapter() {
    if (!$this->adapter) {
      $adapter = new PhutilPersonaAuthAdapter();
      $this->adapter = $adapter;
    }
    return $this->adapter;
  }

  protected function renderLoginForm(
    AphrontRequest $request,
    $mode) {

    Javelin::initBehavior(
      'persona-login',
      array(
        'loginURI' => PhabricatorEnv::getURI($this->getLoginURI()),
      ));

    return $this->renderStandardLoginButton(
      $request,
      $mode,
      array(
        'uri' => $this->getLoginURI(),
        'sigil' => 'persona-login-form',
      ));
  }

  public function isLoginFormAButton() {
    return true;
  }

  public function processLoginRequest(
    PhabricatorAuthLoginController $controller) {

    $request = $controller->getRequest();
    $adapter = $this->getAdapter();

    $account = null;
    $response = null;

    if (!$request->isAjax()) {
      throw new Exception(pht('Expected this request to come via Ajax.'));
    }

    $assertion = $request->getStr('assertion');
    if (!$assertion) {
      throw new Exception(pht('Expected identity assertion.'));
    }

    $adapter->setAssertion($assertion);
    $adapter->setAudience(PhabricatorEnv::getURI('/'));

    try {
      $account_id = $adapter->getAccountID();
    } catch (Exception $ex) {
      // TODO: Handle this in a more user-friendly way.
      throw $ex;
    }

    return array($this->loadOrCreateAccount($account_id), $response);
  }

  protected function getLoginIcon() {
    return 'Persona';
  }

}
