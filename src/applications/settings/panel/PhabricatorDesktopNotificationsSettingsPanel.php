<?php

final class PhabricatorDesktopNotificationsSettingsPanel
  extends PhabricatorSettingsPanel {

  public function isEnabled() {
    $servers = PhabricatorNotificationServerRef::getEnabledAdminServers();
    if (!$servers) {
      return false;
    }

    return PhabricatorApplication::isClassInstalled(
      'PhabricatorNotificationsApplication');
  }

  public function getPanelKey() {
    return 'desktopnotifications';
  }

  public function getPanelName() {
    return pht('Desktop Notifications');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsApplicationsPanelGroup::PANELGROUPKEY;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $preferences = $this->getPreferences();

    $notifications_key = PhabricatorDesktopNotificationsSetting::SETTINGKEY;
    $notifications_value = $preferences->getSettingValue($notifications_key);

    if ($request->isFormPost()) {

      $this->writeSetting(
        $preferences,
        $notifications_key,
        $request->getInt($notifications_key));

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $title = pht('Desktop Notifications');
    $control_id = celerity_generate_unique_node_id();
    $status_id = celerity_generate_unique_node_id();
    $browser_status_id = celerity_generate_unique_node_id();
    $cancel_ask = pht(
      'The dialog asking for permission to send desktop notifications was '.
      'closed without granting permission. Only application notifications '.
      'will be sent.');
    $accept_ask = pht(
      'Click "Save Preference" to persist these changes.');
    $reject_ask = pht(
      'Permission for desktop notifications was denied. Only application '.
      'notifications will be sent.');
    $no_support = pht(
      'This web browser does not support desktop notifications. Only '.
      'application notifications will be sent for this browser regardless of '.
      'this preference.');
    $default_status = phutil_tag(
      'span',
      array(),
      array(
        pht('This browser has not yet granted permission to send desktop '.
        'notifications for this Phabricator instance.'),
        phutil_tag('br'),
        phutil_tag('br'),
        javelin_tag(
          'button',
          array(
            'sigil' => 'desktop-notifications-permission-button',
            'class' => 'green',
          ),
          pht('Grant Permission')),
      ));
    $granted_status = phutil_tag(
      'span',
      array(),
      pht('This browser has been granted permission to send desktop '.
          'notifications for this Phabricator instance.'));
    $denied_status = phutil_tag(
      'span',
      array(),
      pht('This browser has denied permission to send desktop notifications '.
          'for this Phabricator instance. Consult your browser settings / '.
          'documentation to figure out how to clear this setting, do so, '.
          'and then re-visit this page to grant permission.'));

    $message_id = celerity_generate_unique_node_id();

    $message_container = phutil_tag(
      'span',
      array(
        'id' => $message_id,
      ));

    $status_box = id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
      ->setID($status_id)
      ->setIsHidden(true)
      ->appendChild($message_container);

    $control_config = array(
       'controlID' => $control_id,
       'statusID' => $status_id,
       'messageID' => $message_id,
       'browserStatusID' => $browser_status_id,
       'defaultMode' => 0,
       'desktopMode' => 1,
       'cancelAsk' => $cancel_ask,
       'grantedAsk' => $accept_ask,
       'deniedAsk' => $reject_ask,
       'defaultStatus' => $default_status,
       'deniedStatus' => $denied_status,
       'grantedStatus' => $granted_status,
       'noSupport' => $no_support,
     );

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel($title)
        ->setControlID($control_id)
        ->setName($notifications_key)
        ->setValue($notifications_value)
        ->setOptions(
          array(
            1 => pht('Send Desktop Notifications Too'),
            0 => pht('Send Application Notifications Only'),
          ))
        ->setCaption(
          pht(
            'Should Phabricator send desktop notifications? These are sent '.
            'in addition to the notifications within the Phabricator '.
            'application.'))
        ->initBehavior(
          'desktop-notifications-control',
          $control_config))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue(pht('Save Preference')));

    $test_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setWorkflow(true)
      ->setText(pht('Send Test Notification'))
      ->setHref('/notification/test/')
      ->setIcon('fa-exclamation-triangle');

    $form_box = id(new PHUIObjectBoxView())
      ->setHeader(
        id(new PHUIHeaderView())
        ->setHeader(pht('Desktop Notifications'))
        ->addActionLink($test_button))
      ->setForm($form)
      ->setInfoView($status_box)
      ->setFormSaved($request->getBool('saved'));

    $browser_status_box = id(new PHUIInfoView())
      ->setID($browser_status_id)
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
      ->setIsHidden(true)
      ->appendChild($default_status);

    return array(
      $form_box,
      $browser_status_box,
    );
  }

}
