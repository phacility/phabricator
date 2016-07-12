<?php

final class PhabricatorPeopleCreateController
  extends PhabricatorPeopleController {

  public function handleRequest(AphrontRequest $request) {
    $admin = $request->getUser();

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $admin,
      $request,
      $this->getApplicationURI());

    $v_type = 'standard';
    if ($request->isFormPost()) {
      $v_type = $request->getStr('type');

      if ($v_type == 'standard' || $v_type == 'bot' || $v_type == 'list') {
        return id(new AphrontRedirectResponse())->setURI(
          $this->getApplicationURI('new/'.$v_type.'/'));
      }
    }

    $title = pht('Create New User');

    $standard_caption = pht(
      'Create a standard user account. These users can log in to Phabricator, '.
      'use the web interface and API, and receive email.');

    $standard_admin = pht(
      'Administrators are limited in their ability to access or edit these '.
      'accounts after account creation.');

    $bot_caption = pht(
      'Create a bot/script user account, to automate interactions with other '.
      'systems. These users can not use the web interface, but can use the '.
      'API.');

    $bot_admin = pht(
      'Administrators have greater access to edit these accounts.');

    $types = array();

    $can_create = $this->hasApplicationCapability(
      PeopleCreateUsersCapability::CAPABILITY);
    if ($can_create) {
      $types[] = array(
        'type' => 'standard',
        'name' => pht('Create Standard User'),
        'help' => pht('Create a standard user account.'),
      );
    }

    $types[] = array(
      'type' => 'bot',
      'name' => pht('Create Bot User'),
      'help' => pht('Create a new user for use with automated scripts.'),
    );

    $types[] = array(
      'type' => 'list',
      'name' => pht('Create Mailing List User'),
      'help' => pht(
        'Create a mailing list user to represent an existing, external '.
        'mailing list like a Google Group or a Mailman list.'),
    );

    $buttons = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Account Type'))
      ->setName('type')
      ->setValue($v_type);

    foreach ($types as $type) {
      $buttons->addButton($type['type'], $type['name'], $type['help']);
    }

    $form = id(new AphrontFormView())
      ->setUser($admin)
      ->appendRemarkupInstructions(
        pht(
          'Choose the type of user account to create. For a detailed '.
          'explanation of user account types, see [[ %s | User Guide: '.
          'Account Roles ]].',
          PhabricatorEnv::getDoclink('User Guide: Account Roles')))
      ->appendChild($buttons)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($this->getApplicationURI())
          ->setValue(pht('Continue')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-user');

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('User'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
