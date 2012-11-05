<?php

final class PhabricatorOAuthUnlinkController extends PhabricatorAuthController {

  private $provider;

  public function willProcessRequest(array $data) {
    $this->provider = PhabricatorOAuthProvider::newProvider($data['provider']);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $provider = $this->provider;

    if ($provider->isProviderLinkPermanent()) {
      throw new Exception(
        "You may not unlink accounts from this OAuth provider.");
    }

    $provider_key = $provider->getProviderKey();

    $oauth_info = id(new PhabricatorUserOAuthInfo())->loadOneWhere(
      'userID = %d AND oauthProvider = %s',
      $user->getID(),
      $provider_key);

    if (!$oauth_info) {
      return new Aphront400Response();
    }

    if (!$request->isDialogFormPost()) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);
      $dialog->setTitle('Really unlink account?');
      $dialog->appendChild(
        '<p><strong>You will not be able to login</strong> using this account '.
        'once you unlink it. Continue?</p>');
      $dialog->addSubmitButton('Unlink Account');
      $dialog->addCancelButton($provider->getSettingsPanelURI());

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $oauth_info->delete();

    return id(new AphrontRedirectResponse())
      ->setURI($provider->getSettingsPanelURI());
  }

}
