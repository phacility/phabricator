<?php

final class PhabricatorAuthSetPasswordController
  extends PhabricatorAuthController {

  public function shouldAllowPartialSessions() {
    return true;
  }

  public function shouldAllowLegallyNonCompliantUsers() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    if (!PhabricatorPasswordAuthProvider::getPasswordProvider()) {
      return new Aphront404Response();
    }

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      '/');

    $key = $request->getStr('key');
    $password_type = PhabricatorAuthPasswordResetTemporaryTokenType::TOKENTYPE;
    if (!$key) {
      return new Aphront404Response();
    }

    $auth_token = id(new PhabricatorAuthTemporaryTokenQuery())
      ->setViewer($viewer)
      ->withTokenResources(array($viewer->getPHID()))
      ->withTokenTypes(array($password_type))
      ->withTokenCodes(array(PhabricatorHash::weakDigest($key)))
      ->withExpired(false)
      ->executeOne();
    if (!$auth_token) {
      return new Aphront404Response();
    }

    $content_source = PhabricatorContentSource::newFromRequest($request);
    $account_type = PhabricatorAuthPassword::PASSWORD_TYPE_ACCOUNT;

    $password_objects = id(new PhabricatorAuthPasswordQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($viewer->getPHID()))
      ->withPasswordTypes(array($account_type))
      ->withIsRevoked(false)
      ->execute();
    if ($password_objects) {
      $password_object = head($password_objects);
      $has_password = true;
    } else {
      $password_object = PhabricatorAuthPassword::initializeNewPassword(
        $viewer,
        $account_type);
      $has_password = false;
    }

    $engine = id(new PhabricatorAuthPasswordEngine())
      ->setViewer($viewer)
      ->setContentSource($content_source)
      ->setPasswordType($account_type)
      ->setObject($viewer);

    $e_password = true;
    $e_confirm = true;
    $errors = array();
    if ($request->isFormPost()) {
      $password = $request->getStr('password');
      $confirm = $request->getStr('confirm');

      $password_envelope = new PhutilOpaqueEnvelope($password);
      $confirm_envelope = new PhutilOpaqueEnvelope($confirm);

      try {
        $engine->checkNewPassword($password_envelope, $confirm_envelope, true);
        $e_password = null;
        $e_confirm = null;
      } catch (PhabricatorAuthPasswordException $ex) {
        $errors[] = $ex->getMessage();
        $e_password = $ex->getPasswordError();
        $e_confirm = $ex->getConfirmError();
      }

      if (!$errors) {
        $password_object
          ->setPassword($password_envelope, $viewer)
          ->save();

        // Destroy the token.
        $auth_token->delete();

        return id(new AphrontRedirectResponse())->setURI('/');
      }
    }

    $min_len = PhabricatorEnv::getEnvConfig('account.minimum-password-length');
    $min_len = (int)$min_len;

    $len_caption = null;
    if ($min_len) {
      $len_caption = pht('Minimum password length: %d characters.', $min_len);
    }

    if ($has_password) {
      $title = pht('Reset Password');
      $crumb = pht('Reset Password');
      $submit = pht('Reset Password');
    } else {
      $title = pht('Set Password');
      $crumb = pht('Set Password');
      $submit = pht('Set Account Password');
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->addHiddenInput('key', $key)
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setDisableAutocomplete(true)
          ->setLabel(pht('New Password'))
          ->setError($e_password)
          ->setName('password'))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setDisableAutocomplete(true)
          ->setLabel(pht('Confirm Password'))
          ->setCaption($len_caption)
          ->setError($e_confirm)
          ->setName('confirm'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/', pht('Skip This Step'))
          ->setValue($submit));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setForm($form);

    $main_view = id(new PHUITwoColumnView())
      ->setFooter($form_box);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($crumb)
      ->setBorder(true);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($main_view);
  }
}
