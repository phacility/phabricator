<?php

final class PhabricatorConduitTokenHandshakeController
  extends PhabricatorConduitController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      '/');

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $token = PhabricatorConduitToken::initializeNewToken(
        $viewer->getPHID(),
        PhabricatorConduitToken::TYPE_COMMANDLINE);
      $token->save();
    unset($unguarded);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht(
          'Copy-paste the API Token below to grant access to your account.'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('API Token'))
          ->setValue($token->getToken()))
      ->appendRemarkupInstructions(
        pht(
          'This will authorize the requesting script to act on your behalf '.
          'permanently, like giving the script your account password.'))
      ->appendRemarkupInstructions(
        pht(
          'If you change your mind, you can revoke this token later in '.
          '{nav icon=wrench,name=Settings > Conduit API Tokens}.'));

    return $this->newDialog()
      ->setTitle(pht('Grant Account Access'))
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->appendForm($form)
      ->addCancelButton('/');
  }

}
