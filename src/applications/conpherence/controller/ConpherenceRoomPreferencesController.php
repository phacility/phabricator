<?php

final class ConpherenceRoomPreferencesController
  extends ConpherenceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $conpherence_id = $request->getURIData('id');

    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($viewer)
      ->withIDs(array($conpherence_id))
      ->executeOne();
    if (!$conpherence) {
      return new Aphront404Response();
    }

    $view_uri = $conpherence->getURI();

    $participant = $conpherence->getParticipantIfExists($viewer->getPHID());
    if (!$participant) {
      if ($viewer->isLoggedIn()) {
        $text = pht(
          'Notification settings are available after joining the room.');
      } else {
        $text = pht(
          'Notification settings are available after logging in and joining '.
          'the room.');
      }
      return $this->newDialog()
        ->setTitle(pht('Room Preferences'))
        ->addCancelButton($view_uri)
        ->appendParagraph($text);
    }

    // Save the data and redirect
    if ($request->isFormPost()) {
      $notifications = $request->getStr('notifications');
      $sounds = $request->getArr('sounds');
      $theme = $request->getStr('theme');

      $participant->setSettings(array(
        'notifications' => $notifications,
        'sounds' => $sounds,
        'theme' => $theme,
      ));
      $participant->save();

      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }

    $notification_key = PhabricatorConpherenceNotificationsSetting::SETTINGKEY;
    $notification_default = $viewer->getUserSetting($notification_key);

    $sound_key = PhabricatorConpherenceSoundSetting::SETTINGKEY;
    $sound_default = $viewer->getUserSetting($sound_key);

    $settings = $participant->getSettings();
    $notifications = idx($settings, 'notifications', $notification_default);
    $theme = idx($settings, 'theme', ConpherenceRoomSettings::COLOR_LIGHT);

    $sounds = idx($settings, 'sounds', array());
    $map = PhabricatorConpherenceSoundSetting::getDefaultSound($sound_default);
    $receive = idx($sounds,
      ConpherenceRoomSettings::SOUND_RECEIVE,
      $map[ConpherenceRoomSettings::SOUND_RECEIVE]);
    $mention = idx($sounds,
      ConpherenceRoomSettings::SOUND_MENTION,
      $map[ConpherenceRoomSettings::SOUND_MENTION]);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormRadioButtonControl())
          ->setLabel(pht('Notify'))
          ->addButton(
          PhabricatorConpherenceNotificationsSetting::VALUE_CONPHERENCE_EMAIL,
          PhabricatorConpherenceNotificationsSetting::getSettingLabel(
          PhabricatorConpherenceNotificationsSetting::VALUE_CONPHERENCE_EMAIL),
            '')
          ->addButton(
          PhabricatorConpherenceNotificationsSetting::VALUE_CONPHERENCE_NOTIFY,
          PhabricatorConpherenceNotificationsSetting::getSettingLabel(
          PhabricatorConpherenceNotificationsSetting::VALUE_CONPHERENCE_NOTIFY),
            '')
          ->setName('notifications')
          ->setValue($notifications))
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('New Message'))
            ->setName('sounds['.ConpherenceRoomSettings::SOUND_RECEIVE.']')
            ->setOptions(ConpherenceRoomSettings::getDropdownSoundMap())
            ->setValue($receive))
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Theme'))
            ->setName('theme')
            ->setOptions(ConpherenceRoomSettings::getThemeMap())
            ->setValue($theme));

    return $this->newDialog()
      ->setTitle(pht('Room Preferences'))
      ->appendForm($form)
      ->addCancelButton($view_uri)
      ->addSubmitButton(pht('Save'));
  }

}
