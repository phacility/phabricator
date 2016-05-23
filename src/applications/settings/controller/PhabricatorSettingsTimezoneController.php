<?php

final class PhabricatorSettingsTimezoneController
  extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $client_offset = $request->getURIData('offset');
    $client_offset = (int)$client_offset;

    $timezones = DateTimeZone::listIdentifiers();
    $now = new DateTime('@'.PhabricatorTime::getNow());

    $options = array(
      'ignore' => pht('Ignore Conflict'),
    );

    foreach ($timezones as $identifier) {
      $zone = new DateTimeZone($identifier);
      $offset = -($zone->getOffset($now) / 60);
      if ($offset == $client_offset) {
        $options[$identifier] = $identifier;
      }
    }

    $settings_help = pht(
      'You can change your date and time preferences in Settings.');

    if ($request->isFormPost()) {
      $timezone = $request->getStr('timezone');

      $pref_ignore = PhabricatorUserPreferences::PREFERENCE_IGNORE_OFFSET;

      $preferences = $viewer->loadPreferences();

      if ($timezone == 'ignore') {
        $preferences
          ->setPreference($pref_ignore, $client_offset)
          ->save();

        return $this->newDialog()
          ->setTitle(pht('Conflict Ignored'))
          ->appendParagraph(
            pht(
              'The conflict between your browser and profile timezone '.
              'settings will be ignored.'))
          ->appendParagraph($settings_help)
          ->addCancelButton('/', pht('Done'));
      }

      if (isset($options[$timezone])) {
        $preferences
          ->setPreference($pref_ignore, null)
          ->save();

        $viewer
          ->setTimezoneIdentifier($timezone)
          ->save();
      }
    }

    $server_offset = $viewer->getTimeZoneOffset();

    if ($client_offset == $server_offset) {
      return $this->newDialog()
        ->setTitle(pht('Timezone Calibrated'))
        ->appendParagraph(
          pht(
            'Your browser timezone and profile timezone are now '.
            'in agreement (%s).',
            $this->formatOffset($client_offset)))
        ->appendParagraph($settings_help)
        ->addCancelButton('/', pht('Done'));
    }

    // If we have a guess at the timezone from the client, select it as the
    // default.
    $guess = $request->getStr('guess');
    if (empty($options[$guess])) {
      $guess = 'ignore';
    }

    $current_zone = $viewer->getTimezoneIdentifier();
    $current_zone = phutil_tag('strong', array(), $current_zone);

    $form = id(new AphrontFormView())
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Current Setting'))
          ->setValue($current_zone))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('timezone')
          ->setLabel(pht('New Setting'))
          ->setOptions($options)
          ->setValue($guess));

    return $this->newDialog()
      ->setTitle(pht('Adjust Timezone'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendParagraph(
        pht(
          'Your browser timezone (%s) differs from your profile timezone '.
          '(%s). You can ignore this conflict or adjust your profile setting '.
          'to match your client.',
          $this->formatOffset($client_offset),
          $this->formatOffset($server_offset)))
      ->appendForm($form)
      ->addCancelButton(pht('Cancel'))
      ->addSubmitButton(pht('Change Timezone'));
  }

  private function formatOffset($offset) {
    $offset = $offset / 60;

    if ($offset >= 0) {
      return pht('GMT-%d', $offset);
    } else {
      return pht('GMT+%d', -$offset);
    }
  }

}
