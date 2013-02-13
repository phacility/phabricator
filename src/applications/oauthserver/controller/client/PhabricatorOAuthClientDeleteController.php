<?php

/**
 * @group oauthserver
 */
final class PhabricatorOAuthClientDeleteController
extends PhabricatorOAuthClientBaseController {

  public function processRequest() {
    $phid          = $this->getClientPHID();
    $title         = 'Delete OAuth Client';
    $request       = $this->getRequest();
    $current_user  = $request->getUser();
    $client = id(new PhabricatorOAuthServerClient())
      ->loadOneWhere('phid = %s',
                     $phid);

    if (empty($client)) {
      return new Aphront404Response();
    }
    if ($client->getCreatorPHID() != $current_user->getPHID()) {
      $message = 'Access denied to client with phid '.$phid.'. '.
                 'Only the user who created the client has permission to '.
                 'delete the client.';
      return id(new Aphront403Response())
        ->setForbiddenText($message);
    }

    if ($request->isFormPost()) {
      $client->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/oauthserver/client/?deleted=1');
    }

    $client_name = phutil_escape_html($client->getName());
    $title .= ' '.$client_name;

    $dialog = new AphrontDialogView();
    $dialog->setUser($current_user);
    $dialog->setTitle($title);
    $dialog->appendChild(
      '<p>Are you sure you want to delete this client?</p>'
    );
    $dialog->addSubmitButton();
    $dialog->addCancelButton($client->getEditURI());
    return id(new AphrontDialogResponse())->setDialog($dialog);

  }
}
