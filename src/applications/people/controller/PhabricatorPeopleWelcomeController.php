<?php

final class PhabricatorPeopleWelcomeController
  extends PhabricatorPeopleController {

  public function shouldRequireAdmin() {
    // You need to be an administrator to actually send welcome email, but
    // we let anyone hit this page so they can get a nice error dialog
    // explaining the issue.
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $admin = $this->getViewer();

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($admin)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $id = $user->getID();
    $profile_uri = "/people/manage/{$id}/";

    $welcome_engine = id(new PhabricatorPeopleWelcomeMailEngine())
      ->setSender($admin)
      ->setRecipient($user);

    try {
      $welcome_engine->validateMail();
    } catch (PhabricatorPeopleMailEngineException $ex) {
      return $this->newDialog()
        ->setTitle($ex->getTitle())
        ->appendParagraph($ex->getBody())
        ->addCancelButton($profile_uri, pht('Done'));
    }

    $v_message = $request->getStr('message');

    if ($request->isFormPost()) {
      if (strlen($v_message)) {
        $welcome_engine->setWelcomeMessage($v_message);
      }

      $welcome_engine->sendMail();
      return id(new AphrontRedirectResponse())->setURI($profile_uri);
    }

    $default_message = PhabricatorAuthMessage::loadMessage(
      $admin,
      PhabricatorAuthWelcomeMailMessageType::MESSAGEKEY);
    if ($default_message && strlen($default_message->getMessageText())) {
      $message_instructions = pht(
        'The email will identify you as the sender. You may optionally '.
        'replace the [[ %s | default custom mail body ]] with different text '.
        'by providing a message below.',
        $default_message->getURI());
    } else {
      $message_instructions = pht(
        'The email will identify you as the sender. You may optionally '.
        'include additional text in the mail body by specifying it below.');
    }

    $form = id(new AphrontFormView())
      ->setViewer($admin)
      ->appendRemarkupInstructions(
        pht(
          'This workflow will send this user ("%s") a copy of the "Welcome to '.
          '%s" email that users normally receive when their '.
          'accounts are created by an administrator.',
          $user->getUsername(),
          PlatformSymbols::getPlatformServerName()))
      ->appendRemarkupInstructions(
        pht(
          'The email will contain a link that the user may use to log in '.
          'to their account. This link bypasses authentication requirements '.
          'and allows them to log in without credentials. Sending a copy of '.
          'this email can be useful if the original was lost or never sent.'))
      ->appendRemarkupInstructions($message_instructions)
      ->appendControl(
        id(new PhabricatorRemarkupControl())
          ->setName('message')
          ->setLabel(pht('Custom Message'))
          ->setValue($v_message));

    return $this->newDialog()
      ->setTitle(pht('Send Welcome Email'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendForm($form)
      ->addSubmitButton(pht('Send Email'))
      ->addCancelButton($profile_uri);
  }

}
