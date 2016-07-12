<?php

final class PhabricatorAuthConfirmLinkController
  extends PhabricatorAuthController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $accountkey = $request->getURIData('akey');

    $result = $this->loadAccountForRegistrationOrLinking($accountkey);
    list($account, $provider, $response) = $result;

    if ($response) {
      return $response;
    }

    if (!$provider->shouldAllowAccountLink()) {
      return $this->renderError(pht('This account is not linkable.'));
    }

    $panel_uri = '/settings/panel/external/';

    if ($request->isFormPost()) {
      $account->setUserPHID($viewer->getPHID());
      $account->save();

      $this->clearRegistrationCookies();

      // TODO: Send the user email about the new account link.

      return id(new AphrontRedirectResponse())->setURI($panel_uri);
    }

    // TODO: Provide more information about the external account. Clicking
    // through this form blindly is dangerous.

    // TODO: If the user has password authentication, require them to retype
    // their password here.

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Confirm %s Account Link', $provider->getProviderName()))
      ->addCancelButton($panel_uri)
      ->addSubmitButton(pht('Confirm Account Link'));

    $form = id(new PHUIFormLayoutView())
      ->setFullWidth(true)
      ->appendChild(
        phutil_tag(
          'div',
          array(
            'class' => 'aphront-form-instructions',
          ),
          pht(
            'Confirm the link with this %s account. This account will be '.
            'able to log in to your Phabricator account.',
            $provider->getProviderName())))
      ->appendChild(
        id(new PhabricatorAuthAccountView())
          ->setUser($viewer)
          ->setExternalAccount($account)
          ->setAuthProvider($provider));

    $dialog->appendChild($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Confirm Link'), $panel_uri);
    $crumbs->addTextCrumb($provider->getProviderName());
    $crumbs->setBorder(true);

    return $this->newPage()
      ->setTitle(pht('Confirm External Account Link'))
      ->setCrumbs($crumbs)
      ->appendChild($dialog);
  }


}
