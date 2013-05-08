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
          $errors[] = pht('Real name must be nonempty.');
          $e_realname = pht('Required');
        }
      }

      $new_timezone = $request->getStr('timezone');
      if (in_array($new_timezone, DateTimeZone::listIdentifiers(), true)) {
        $user->setTimezoneIdentifier($new_timezone);
      } else {
        $errors[] = pht('The selected timezone is not a valid timezone.');
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
        $notice->setTitle(pht('Changes Saved'));
        $notice->appendChild(
          phutil_tag('p', array(), pht('Your changes have been saved.')));
        $notice = $notice->render();
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setTitle(pht('Form Errors'));
      $notice->setErrors($errors);
      $notice = $notice->render();
    }

    $timezone_ids = DateTimeZone::listIdentifiers();
    $timezone_id_map = array_fuse($timezone_ids);

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Username'))
          ->setValue($user->getUsername()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Real Name'))
          ->setName('realname')
          ->setError($e_realname)
          ->setValue($user->getRealName())
          ->setDisabled(!$editable))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Timezone'))
          ->setName('timezone')
          ->setOptions($timezone_id_map)
          ->setValue($user->getTimezoneIdentifier()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save')));

    $header = new PhabricatorHeaderView();
    $header->setHeader(pht('Account Settings'));

    return array(
      $notice,
      $header,
      $form,
    );
  }
}
