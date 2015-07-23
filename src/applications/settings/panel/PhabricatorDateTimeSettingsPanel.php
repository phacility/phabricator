<?php

final class PhabricatorDateTimeSettingsPanel extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'datetime';
  }

  public function getPanelName() {
    return pht('Date and Time');
  }

  public function getPanelGroup() {
    return pht('Account Information');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $username = $user->getUsername();

    $pref_time = PhabricatorUserPreferences::PREFERENCE_TIME_FORMAT;
    $pref_date = PhabricatorUserPreferences::PREFERENCE_DATE_FORMAT;
    $pref_week_start = PhabricatorUserPreferences::PREFERENCE_WEEK_START_DAY;
    $preferences = $user->loadPreferences();

    $errors = array();
    if ($request->isFormPost()) {
      $new_timezone = $request->getStr('timezone');
      if (in_array($new_timezone, DateTimeZone::listIdentifiers(), true)) {
        $user->setTimezoneIdentifier($new_timezone);
      } else {
        $errors[] = pht('The selected timezone is not a valid timezone.');
      }

      $preferences
        ->setPreference(
          $pref_time,
          $request->getStr($pref_time))
        ->setPreference(
          $pref_date,
          $request->getStr($pref_date))
        ->setPreference(
          $pref_week_start,
          $request->getStr($pref_week_start));

      if (!$errors) {
        $preferences->save();
        $user->save();
        return id(new AphrontRedirectResponse())
          ->setURI($this->getPanelURI('?saved=true'));
      }
    }

    $timezone_ids = DateTimeZone::listIdentifiers();
    $timezone_id_map = array_fuse($timezone_ids);

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Timezone'))
          ->setName('timezone')
          ->setOptions($timezone_id_map)
          ->setValue($user->getTimezoneIdentifier()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Time-of-Day Format'))
          ->setName($pref_time)
          ->setOptions(array(
              'g:i A' => pht('12-hour (2:34 PM)'),
              'H:i' => pht('24-hour (14:34)'),
            ))
          ->setCaption(
            pht('Format used when rendering a time of day.'))
          ->setValue($preferences->getPreference($pref_time)))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Date Format'))
          ->setName($pref_date)
          ->setOptions(array(
              'Y-m-d' => pht('ISO 8601 (2000-02-28)'),
              'n/j/Y' => pht('US (2/28/2000)'),
              'd-m-Y' => pht('European (28-02-2000)'),
            ))
          ->setCaption(
            pht('Format used when rendering a date.'))
          ->setValue($preferences->getPreference($pref_date)))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Week Starts On'))
          ->setOptions($this->getWeekDays())
          ->setName($pref_week_start)
          ->setCaption(
            pht('Calendar weeks will start with this day.'))
          ->setValue($preferences->getPreference($pref_week_start, 0)))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Account Settings')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Date and Time Settings'))
      ->setFormSaved($request->getStr('saved'))
      ->setFormErrors($errors)
      ->setForm($form);

    return array(
      $form_box,
    );
  }

  private function getWeekDays() {
    return array(
      pht('Sunday'),
      pht('Monday'),
      pht('Tuesday'),
      pht('Wednesday'),
      pht('Thursday'),
      pht('Friday'),
      pht('Saturday'),
    );
  }
}
