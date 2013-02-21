<?php

/**
 * @group oauthserver
 */
final class PhabricatorOAuthClientAuthorizationEditController
extends PhabricatorOAuthClientAuthorizationBaseController {

  public function processRequest() {
    $phid          = $this->getAuthorizationPHID();
    $title         = 'Edit OAuth Client Authorization';
    $request       = $this->getRequest();
    $current_user  = $request->getUser();
    $authorization = id(new PhabricatorOAuthClientAuthorization())
      ->loadOneWhere('phid = %s',
                     $phid);

    if (empty($authorization)) {
      return new Aphront404Response();
    }
    if ($authorization->getUserPHID() != $current_user->getPHID()) {
      $message = 'Access denied to client authorization with phid '.$phid.'. '.
                 'Only the user who authorized the client has permission to '.
                 'edit the authorization.';
      return id(new Aphront403Response())
        ->setForbiddenText($message);
    }

    if ($request->isFormPost()) {
      $scopes = PhabricatorOAuthServerScope::getScopesFromRequest($request);
      $authorization->setScope($scopes);
      $authorization->save();
      return id(new AphrontRedirectResponse())
        ->setURI('/oauthserver/clientauthorization/?edited='.$phid);
    }

    $client_phid = $authorization->getClientPHID();
    $client      = id(new PhabricatorOAuthServerClient())
      ->loadOneWhere('phid = %s',
                     $client_phid);

    $created = phabricator_datetime($authorization->getDateCreated(),
                                    $current_user);
    $updated = phabricator_datetime($authorization->getDateModified(),
                                    $current_user);

    $panel = new AphrontPanelView();
    $delete_button = phutil_tag(
      'a',
      array(
        'href' => $authorization->getDeleteURI(),
        'class' => 'grey button',
      ),
      'Delete OAuth Client Authorization');
    $panel->addButton($delete_button);
    $panel->setHeader($title);

    $form = id(new AphrontFormView())
      ->setUser($current_user)
      ->appendChild(
        id(new AphrontFormMarkupControl())
        ->setLabel('Client')
        ->setValue(
          phutil_tag(
            'a',
            array(
              'href' => $client->getViewURI(),
            ),
            $client->getName())))
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setLabel('Created')
        ->setValue($created))
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setLabel('Last Updated')
        ->setValue($updated))
      ->appendChild(
        PhabricatorOAuthServerScope::getCheckboxControl(
          $authorization->getScope()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Save OAuth Client Authorization')
        ->addCancelButton('/oauthserver/clientauthorization/'));

    $panel->appendChild($form);
    return $this->buildStandardPageResponse(
      $panel,
      array('title' => $title));
  }
}
