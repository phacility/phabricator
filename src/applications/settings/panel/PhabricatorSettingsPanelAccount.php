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
    $username = $user->getUsername();

    $pref_time = PhabricatorUserPreferences::PREFERENCE_TIME_FORMAT;
    $preferences = $user->loadPreferences();

    $errors = array();
    if ($request->isFormPost()) {
      $new_timezone = $request->getStr('timezone');
      if (in_array($new_timezone, DateTimeZone::listIdentifiers(), true)) {
        $user->setTimezoneIdentifier($new_timezone);
      } else {
        $errors[] = pht('The selected timezone is not a valid timezone.');
      }

      $sex = $request->getStr('sex');
      $sexes = array(PhutilPerson::SEX_MALE, PhutilPerson::SEX_FEMALE);
      if (in_array($sex, $sexes)) {
        $user->setSex($sex);
      } else {
        $user->setSex(null);
      }

      // Checked in runtime.
      $user->setTranslation($request->getStr('translation'));

      $preferences->setPreference($pref_time, $request->getStr($pref_time));

      if (!$errors) {
        $preferences->save();
        $user->save();
        return id(new AphrontRedirectResponse())
          ->setURI($this->getPanelURI('?saved=true'));
      }
    }

    $timezone_ids = DateTimeZone::listIdentifiers();
    $timezone_id_map = array_fuse($timezone_ids);

    $label_unknown = pht('%s updated their profile', $username);
    $label_her = pht('%s updated her profile', $username);
    $label_his = pht('%s updated his profile', $username);

    $sexes = array(
      PhutilPerson::SEX_UNKNOWN => $label_unknown,
      PhutilPerson::SEX_MALE => $label_his,
      PhutilPerson::SEX_FEMALE => $label_her,
    );

    $translations = array();
    $symbols = id(new PhutilSymbolLoader())
      ->setType('class')
      ->setAncestorClass('PhabricatorTranslation')
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();
    foreach ($symbols as $symbol) {
      $class = $symbol['name'];
      $translations[$class] = newv($class, array())->getName();
    }
    asort($translations);
    $default = PhabricatorEnv::newObjectFromConfig('translation.provider');
    $translations = array(
      '' => pht('Server Default (%s)', $default->getName()),
    ) + $translations;

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Timezone'))
          ->setName('timezone')
          ->setOptions($timezone_id_map)
          ->setValue($user->getTimezoneIdentifier()))
      ->appendRemarkupInstructions(pht("**Choose the pronoun you prefer:**"))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setOptions($sexes)
          ->setLabel(pht('Pronoun'))
          ->setName('sex')
          ->setValue($user->getSex()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setOptions($translations)
          ->setLabel(pht('Translation'))
          ->setName('translation')
          ->setValue($user->getTranslation()))
      ->appendRemarkupInstructions(
        pht(
          "**Custom Date and Time Formats**\n\n".
          "You can specify custom formats which will be used when ".
          "rendering dates and times of day. Examples:\n\n".
          "| Format  | Example  | Notes |\n".
          "| ------  | -------- | ----- |\n".
          "| `g:i A` | 2:34 PM  | Default 12-hour time. |\n".
          "| `G.i a` | 02.34 pm | Alternate 12-hour time. |\n".
          "| `H:i`   | 14:34    | 24-hour time. |\n".
          "\n\n".
          "You can find a [[%s | full reference in the PHP manual]].",
          "http://www.php.net/manual/en/function.date.php"))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Time-of-Day Format'))
          ->setName($pref_time)
          ->setCaption(
            pht('Format used when rendering a time of day.'))
          ->setValue($preferences->getPreference($pref_time)))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Account Settings')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Account Settings'))
      ->setFormSaved($request->getStr('saved'))
      ->setFormErrors($errors)
      ->setForm($form);

    return array(
      $form_box,
    );
  }
}
