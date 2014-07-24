<?php

final class PhabricatorOAuthClientEditController
  extends PhabricatorOAuthClientController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $phid = $this->getClientPHID();
    if ($phid) {
      $client = id(new PhabricatorOAuthServerClientQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($phid))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$client) {
        return new Aphront404Response();
      }

      $title = pht('Edit OAuth Application: %s', $client->getName());
      $submit_button = pht('Save Application');
      $crumb_text = pht('Edit');
      $cancel_uri = $client->getViewURI();
      $is_new = false;
    } else {
      $this->requireApplicationCapability(
        PhabricatorOAuthServerCreateClientsCapability::CAPABILITY);

      $client = PhabricatorOAuthServerClient::initializeNewClient($viewer);

      $title = pht('Create OAuth Application');
      $submit_button = pht('Create Application');
      $crumb_text = pht('Create Application');
      $cancel_uri = $this->getApplicationURI();
      $is_new = true;
    }

    $errors = array();
    $e_redirect = true;
    $e_name = true;
    if ($request->isFormPost()) {
      $redirect_uri = $request->getStr('redirect_uri');
      $client->setName($request->getStr('name'));
      $client->setRedirectURI($redirect_uri);

      if (!strlen($client->getName())) {
        $errors[] = pht('You must choose a name for this OAuth application.');
        $e_name = pht('Required');
      }

      $server = new PhabricatorOAuthServer();
      $uri = new PhutilURI($redirect_uri);
      if (!$server->validateRedirectURI($uri)) {
        $errors[] = pht(
          'Redirect URI must be a fully qualified domain name '.
          'with no fragments. See %s for more information on the correct '.
          'format.',
          'http://tools.ietf.org/html/draft-ietf-oauth-v2-23#section-3.1.2');
        $e_redirect = pht('Invalid');
      }

      if (!$errors) {
        $client->save();
        $view_uri = $client->getViewURI();
        return id(new AphrontRedirectResponse())->setURI($view_uri);
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($client->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Redirect URI')
          ->setName('redirect_uri')
          ->setValue($client->getRedirectURI())
          ->setError($e_redirect))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($submit_button));

    $crumbs = $this->buildApplicationCrumbs();
    if (!$is_new) {
      $crumbs->addTextCrumb(
        $client->getName(),
        $client->getViewURI());
    }
    $crumbs->addTextCrumb($crumb_text);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }

}
