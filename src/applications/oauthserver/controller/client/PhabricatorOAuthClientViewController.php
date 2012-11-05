<?php

/**
 * @group oauthserver
 */
final class PhabricatorOAuthClientViewController
extends PhabricatorOAuthClientBaseController {

  protected function getFilter() {
    return 'client/view/'.$this->getClientPHID();
  }

  protected function getExtraClientFilters() {
    return array(
      array('url'   => $this->getFilter(),
            'label' => 'View Client')
      );
  }

  public function processRequest() {
    $request       = $this->getRequest();
    $current_user  = $request->getUser();
    $error         = null;
    $phid          = $this->getClientPHID();

    $client = id(new PhabricatorOAuthServerClient())
      ->loadOneWhere('phid = %s',
                     $phid);
    $title  = 'View OAuth Client';

    // validate the client
    if (empty($client)) {
      $message = 'No client found with id '.$phid.'.';
      return $this->buildStandardPageResponse(
        $this->buildErrorView($message),
        array('title' => $title)
      );
    }

    $panel = new AphrontPanelView();
    $panel->setHeader($title);

    $form = id(new AphrontFormView())
      ->setUser($current_user)
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setLabel('Name')
        ->setValue($client->getName())
      )
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setLabel('ID')
        ->setValue($phid)
      );
    if ($current_user->getPHID() == $client->getCreatorPHID()) {
      $form
        ->appendChild(
          id(new AphrontFormStaticControl())
          ->setLabel('Secret')
          ->setValue($client->getSecret())
        );
    }
    $form
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setLabel('Redirect URI')
        ->setValue($client->getRedirectURI())
      );
    $created = phabricator_datetime($client->getDateCreated(),
                                    $current_user);
    $updated = phabricator_datetime($client->getDateModified(),
                                    $current_user);
    $form
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setLabel('Created')
        ->setValue($created)
      )
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setLabel('Last Updated')
        ->setValue($updated)
      );
    $panel->appendChild($form);
    $admin_panel = null;
    if ($client->getCreatorPHID() == $current_user->getPHID()) {
      $edit_button = phutil_render_tag(
        'a',
        array(
          'href'  => $client->getEditURI(),
          'class' => 'grey button',
        ),
        'Edit OAuth Client');
      $panel->addButton($edit_button);

      $create_authorization_form = id(new AphrontFormView())
        ->setUser($current_user)
        ->addHiddenInput('action', 'testclientauthorization')
        ->addHiddenInput('client_phid', $phid)
        ->setAction('/oauthserver/test/')
        ->appendChild(
          id(new AphrontFormSubmitControl())
          ->setValue('Create Scopeless Test Authorization')
        );
      $admin_panel = id(new AphrontPanelView())
        ->setHeader('Admin Tools')
        ->appendChild($create_authorization_form);
    }

    return $this->buildStandardPageResponse(
      array($error,
            $panel,
            $admin_panel
    ),
    array('title' => $title)
  );
  }
}
