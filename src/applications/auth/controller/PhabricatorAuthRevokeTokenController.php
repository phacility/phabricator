<?php

final class PhabricatorAuthRevokeTokenController
  extends PhabricatorAuthController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $is_all = ($id === 'all');

    $query = id(new PhabricatorAuthTemporaryTokenQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($viewer->getPHID()));
    if (!$is_all) {
      $query->withIDs(array($id));
    }

    $tokens = $query->execute();
    foreach ($tokens as $key => $token) {
      if (!$token->isRevocable()) {
        // Don't revoke unrevocable tokens.
        unset($tokens[$key]);
      }
    }

    $panel_uri = '/settings/panel/tokens/';

    if (!$tokens) {
      return $this->newDialog()
        ->setTitle(pht('No Matching Tokens'))
        ->appendParagraph(
          pht('There are no matching tokens to revoke.'))
        ->appendParagraph(
          pht(
            '(Some types of token can not be revoked, and you can not revoke '.
            'tokens which have already expired.)'))
        ->addCancelButton($panel_uri);
    }

    if ($request->isDialogFormPost()) {
      foreach ($tokens as $token) {
        $token->revokeToken();
      }
      return id(new AphrontRedirectResponse())->setURI($panel_uri);
    }

    if ($is_all) {
      $title = pht('Revoke Tokens?');
      $short = pht('Revoke Tokens');
      $body = pht(
        'Really revoke all tokens? Among other temporary authorizations, '.
        'this will disable any outstanding password reset or account '.
        'recovery links.');
    } else {
      $title = pht('Revoke Token?');
      $short = pht('Revoke Token');
      $body = pht(
        'Really revoke this token? Any temporary authorization it enables '.
        'will be disabled.');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setShortTitle($short)
      ->appendParagraph($body)
      ->addSubmitButton(pht('Revoke'))
      ->addCancelButton($panel_uri);
  }


}
