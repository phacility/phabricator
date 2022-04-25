<?php

final class PhabricatorAuthUnlinkController
  extends PhabricatorAuthController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $account = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $done_uri = '/settings/panel/external/';

    $config = $account->getProviderConfig();
    $provider = $config->getProvider();
    if (!$provider->shouldAllowAccountUnlink()) {
      return $this->renderNotUnlinkableErrorDialog($provider, $done_uri);
    }

	  // Check that this account isn't the last account which can be used to
		// login. We prevent you from removing the last account.
    if ($account->isUsableForLogin()) {
      $other_accounts = id(new PhabricatorExternalAccountQuery())
        ->setViewer($viewer)
        ->withUserPHIDs(array($viewer->getPHID()))
        ->execute();

      $valid_accounts = 0;
      foreach ($other_accounts as $other_account) {
        if ($other_account->isUsableForLogin()) {
          $valid_accounts++;
        }
      }

      if ($valid_accounts < 2) {
				return $this->renderLastUsableAccountErrorDialog($done_uri);
      }
    }

		if ($request->isDialogFormPost()) {
      $account->delete();

			id(new PhabricatorAuthSessionEngine())->terminateLoginSessions(
				$viewer,
				new PhutilOpaqueEnvelope(
					$request->getCookie(PhabricatorCookies::COOKIE_SESSION)));
			return id(new AphrontRedirectResponse())->setURI($done_uri);
		}

    $workflow_key = sprintf(
      'account.unlink(%s)',
      $account->getPHID());

    $hisec_token = id(new PhabricatorAuthSessionEngine())
      ->setWorkflowKey($workflow_key)
      ->requireHighSecurityToken($viewer, $request, $done_uri);

    $account->unlinkAccount();

    id(new PhabricatorAuthSessionEngine())->terminateLoginSessions(
      $viewer,
      new PhutilOpaqueEnvelope(
        $request->getCookie(PhabricatorCookies::COOKIE_SESSION)));

    return id(new AphrontRedirectResponse())->setURI($done_uri);
  }

  private function renderNotUnlinkableErrorDialog(
    PhabricatorAuthProvider $provider,
    $done_uri) {

    return $this->newDialog()
      ->setTitle(pht('Permanent Account Link'))
      ->appendChild(
        pht(
          'You can not unlink this account because the administrator has '.
          'configured this server to make links to "%s" accounts permanent.',
          $provider->getProviderName()))
      ->addCancelButton($done_uri);
  }

	private function renderLastUsableAccountErrorDialog($done_uri) {
    $dialog = id(new AphrontDialogView())
      ->setUser($this->getRequest()->getUser())
      ->setTitle(pht('Last Valid Account'))
      ->appendChild(
        pht(
          'You can not unlink this account because you have no other '.
          'valid login accounts. If you removed it, you would be unable '.
          'to log in. Add another authentication method before removing '.
          'this one.'))
      ->addCancelButton($done_uri);
		return id(new AphrontDialogResponse())->setDialog($dialog);
	}

  private function renderConfirmDialog(
    PhabricatorAuthProviderConfig $config,
    $done_uri) {

    $confirmations[] = 'unlink';
    $provider = $config->getProvider();

    $title = pht('Unlink "%s" Account?', $provider->getProviderName());
    $body = pht(
      'You will no longer be able to use your %s account to '.
      'log in.',
      $provider->getProviderName());

    return $this->newDialog()
      ->setTitle($title)
      ->addHiddenInput('confirmations', implode(',', $confirmations))
      ->appendParagraph($body)
      ->appendParagraph(
        pht(
          'Note: Unlinking an authentication provider will terminate any '.
          'other active login sessions.'))
      ->addSubmitButton(pht('Unlink Account'))
      ->addCancelButton($done_uri);
  }
}
