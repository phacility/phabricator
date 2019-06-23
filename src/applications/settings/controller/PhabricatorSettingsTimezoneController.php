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
        $name = PhabricatorTime::getTimezoneDisplayName($identifier);
        $options[$identifier] = $name;
      }
    }

    $settings_help = pht(
      'You can change your date and time preferences in Settings.');

    $did_calibrate = false;
    if ($request->isFormPost()) {
      $timezone = $request->getStr('timezone');

      $pref_ignore = PhabricatorTimezoneIgnoreOffsetSetting::SETTINGKEY;
      $pref_timezone = PhabricatorTimezoneSetting::SETTINGKEY;

      if ($timezone == 'ignore') {
        $this->writeSettings(
          array(
            $pref_ignore => $client_offset,
          ));

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
        $this->writeSettings(
          array(
            $pref_ignore => null,
            $pref_timezone => $timezone,
          ));

        $did_calibrate = true;
      }
    }

    $server_offset = $viewer->getTimeZoneOffset();

    if (($client_offset == $server_offset) || $did_calibrate) {
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
    // This controller works with client-side (Javascript) offsets, which have
    // the opposite sign we might expect -- for example "UTC-3" is a positive
    // offset. Invert the sign before rendering the offset.
    $offset = -1 * $offset;

    $hours = $offset / 60;
    // Non-integer number of hours off UTC?
    if ($offset % 60) {
      $minutes = abs($offset % 60);
      return pht('UTC%+d:%02d', $hours, $minutes);
    } else {
      return pht('UTC%+d', $hours);
    }
  }

  private function writeSettings(array $map) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $preferences = PhabricatorUserPreferences::loadUserPreferences($viewer);

    $editor = id(new PhabricatorUserPreferencesEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $xactions = array();
    foreach ($map as $key => $value) {
      $xactions[] = $preferences->newTransaction($key, $value);
    }

    $editor->applyTransactions($preferences, $xactions);
  }

}
