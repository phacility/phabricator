<?php

final class PhabricatorPeopleNewController
  extends PhabricatorPeopleController {

  public function handleRequest(AphrontRequest $request) {
    $type = $request->getURIData('type');
    $admin = $request->getUser();

    $is_bot = false;
    $is_list = false;
    switch ($type) {
      case 'standard':
        $this->requireApplicationCapability(
          PeopleCreateUsersCapability::CAPABILITY);
        break;
      case 'bot':
        $is_bot = true;
        break;
      case 'list':
        $is_list = true;
        break;
      default:
        return new Aphront404Response();
    }

    $user = new PhabricatorUser();
    $require_real_name = PhabricatorEnv::getEnvConfig('user.require-real-name');

    $e_username = true;
    $e_realname = $require_real_name ? true : null;
    $e_email    = true;
    $errors = array();

    $welcome_checked = true;

    $new_email = null;

    if ($request->isFormPost()) {
      $welcome_checked = $request->getInt('welcome');

      $user->setUsername($request->getStr('username'));

      $new_email = $request->getStr('email');
      if (!strlen($new_email)) {
        $errors[] = pht('Email is required.');
        $e_email = pht('Required');
      } else if (!PhabricatorUserEmail::isAllowedAddress($new_email)) {
        $e_email = pht('Invalid');
        $errors[] = PhabricatorUserEmail::describeAllowedAddresses();
      } else {
        $e_email = null;
      }

      $user->setRealName($request->getStr('realname'));

      if (!strlen($user->getUsername())) {
        $errors[] = pht('Username is required.');
        $e_username = pht('Required');
      } else if (!PhabricatorUser::validateUsername($user->getUsername())) {
        $errors[] = PhabricatorUser::describeValidUsername();
        $e_username = pht('Invalid');
      } else {
        $e_username = null;
      }

      if (!strlen($user->getRealName()) && $require_real_name) {
        $errors[] = pht('Real name is required.');
        $e_realname = pht('Required');
      } else {
        $e_realname = null;
      }

      if (!$errors) {
        try {

          $email = id(new PhabricatorUserEmail())
            ->setAddress($new_email)
            ->setIsVerified(0);

          // Automatically approve the user, since an admin is creating them.
          $user->setIsApproved(1);

          // If the user is a bot or list, approve their email too.
          if ($is_bot || $is_list) {
            $email->setIsVerified(1);
          }

          id(new PhabricatorUserEditor())
            ->setActor($admin)
            ->createNewUser($user, $email);

          if ($is_bot) {
            id(new PhabricatorUserEditor())
              ->setActor($admin)
              ->makeSystemAgentUser($user, true);
          }

          if ($is_list) {
            id(new PhabricatorUserEditor())
              ->setActor($admin)
              ->makeMailingListUser($user, true);
          }

          if ($welcome_checked && !$is_bot && !$is_list) {
            $user->sendWelcomeEmail($admin);
          }

          $response = id(new AphrontRedirectResponse())
            ->setURI('/p/'.$user->getUsername().'/');
          return $response;
        } catch (AphrontDuplicateKeyQueryException $ex) {
          $errors[] = pht('Username and email must be unique.');

          $same_username = id(new PhabricatorUser())
            ->loadOneWhere('username = %s', $user->getUsername());
          $same_email = id(new PhabricatorUserEmail())
            ->loadOneWhere('address = %s', $new_email);

          if ($same_username) {
            $e_username = pht('Duplicate');
          }

          if ($same_email) {
            $e_email = pht('Duplicate');
          }
        }
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($admin);

    if ($is_bot) {
      $form->appendRemarkupInstructions(
        pht('You are creating a new **bot** user account.'));
    } else if ($is_list) {
      $form->appendRemarkupInstructions(
        pht('You are creating a new **mailing list** user account.'));
    } else {
      $form->appendRemarkupInstructions(
        pht('You are creating a new **standard** user account.'));
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Username'))
          ->setName('username')
          ->setValue($user->getUsername())
          ->setError($e_username))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Real Name'))
          ->setName('realname')
          ->setValue($user->getRealName())
          ->setError($e_realname))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setName('email')
          ->setValue($new_email)
          ->setCaption(PhabricatorUserEmail::describeAllowedAddresses())
          ->setError($e_email));

    if (!$is_bot && !$is_list) {
      $form->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'welcome',
            1,
            pht('Send "Welcome to Phabricator" email with login instructions.'),
            $welcome_checked));
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($this->getApplicationURI())
          ->setValue(pht('Create User')));

    if ($is_bot) {
      $form
        ->appendChild(id(new AphrontFormDividerControl()))
        ->appendRemarkupInstructions(
          pht(
            '**Why do bot accounts need an email address?**'.
            "\n\n".
            'Although bots do not normally receive email from Phabricator, '.
            'they can interact with other systems which require an email '.
            'address. Examples include:'.
            "\n\n".
            "  - If the account takes actions which //send// email, we need ".
            "    an address to use in the //From// header.\n".
            "  - If the account creates commits, Git and Mercurial require ".
            "    an email address for authorship.\n".
            "  - If you send email //to// Phabricator on behalf of the ".
            "    account, the address can identify the sender.\n".
            "  - Some internal authentication functions depend on accounts ".
            "    having an email address.\n".
            "\n\n".
            "The address will automatically be verified, so you do not need ".
            "to be able to receive mail at this address, and can enter some ".
            "invalid or nonexistent (but correctly formatted) address like ".
            "`bot@yourcompany.com` if you prefer."));
    }


    $title = pht('Create New User');

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
