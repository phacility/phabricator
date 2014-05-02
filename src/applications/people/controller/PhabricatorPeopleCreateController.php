<?php

final class PhabricatorPeopleCreateController
  extends PhabricatorPeopleController {

  public function processRequest() {
    $request = $this->getRequest();
    $admin = $request->getUser();

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $admin,
      $request,
      $this->getApplicationURI());

    $v_type = 'standard';
    if ($request->isFormPost()) {
      $v_type = $request->getStr('type');

      if ($v_type == 'standard' || $v_type == 'bot') {
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

    $form = id(new AphrontFormView())
      ->setUser($admin)
      ->appendRemarkupInstructions(
        pht(
          'Choose the type of user account to create. For a detailed '.
          'explanation of user account types, see [[ %s | User Guide: '.
          'Account Roles ]].',
          PhabricatorEnv::getDoclink('User Guide: Account Roles')))
      ->appendChild(
        id(new AphrontFormRadioButtonControl())
          ->setLabel(pht('Account Type'))
          ->setName('type')
          ->setValue($v_type)
          ->addButton(
            'standard',
            pht('Create Standard User'),
            hsprintf('%s<br /><br />%s', $standard_caption, $standard_admin))
          ->addButton(
            'bot',
            pht('Create Bot/Script User'),
            hsprintf('%s<br /><br />%s', $bot_caption, $bot_admin)))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($this->getApplicationURI())
          ->setValue(pht('Continue')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
