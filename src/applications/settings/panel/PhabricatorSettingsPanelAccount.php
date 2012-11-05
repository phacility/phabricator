<?php

final class PhabricatorSettingsPanelAccount
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'account';
  }

  public function getPanelName() {
    return pht('Account');
  }

  public function getPanelGroup() {
    return pht('Account Information');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $editable = PhabricatorEnv::getEnvConfig('account.editable');

    $e_realname = $editable ? true : null;
    $errors = array();
    if ($request->isFormPost()) {

      if ($editable) {
        $user->setRealName($request->getStr('realname'));
        if (!strlen($user->getRealName())) {
          $errors[] = 'Real name must be nonempty.';
          $e_realname = 'Required';
        }
      }

      $new_timezone = $request->getStr('timezone');
      if (in_array($new_timezone, DateTimeZone::listIdentifiers(), true)) {
        $user->setTimezoneIdentifier($new_timezone);
      } else {
        $errors[] = 'The selected timezone is not a valid timezone.';
      }

      if (!$errors) {
        $user->save();
        return id(new AphrontRedirectResponse())
          ->setURI($this->getPanelURI('?saved=true'));
      }
    }

    $notice = null;
    if (!$errors) {
      if ($request->getStr('saved')) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle('Changes Saved');
        $notice->appendChild('<p>Your changes have been saved.</p>');
        $notice = $notice->render();
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setTitle('Form Errors');
      $notice->setErrors($errors);
      $notice = $notice->render();
    }

    $timezone_ids = DateTimeZone::listIdentifiers();
    $timezone_id_map = array_combine($timezone_ids, $timezone_ids);

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Username')
          ->setValue($user->getUsername()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Real Name')
          ->setName('realname')
          ->setError($e_realname)
          ->setValue($user->getRealName())
          ->setDisabled(!$editable))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Timezone')
          ->setName('timezone')
          ->setOptions($timezone_id_map)
          ->setValue($user->getTimezoneIdentifier()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Account Settings');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return array(
      $notice,
      $panel,
    );
  }
}
