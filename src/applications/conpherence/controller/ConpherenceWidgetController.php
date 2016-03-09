<?php

final class ConpherenceWidgetController extends ConpherenceController {

  private $userPreferences;

  public function setUserPreferences(PhabricatorUserPreferences $pref) {
    $this->userPreferences = $pref;
    return $this;
  }

  public function getUserPreferences() {
    return $this->userPreferences;
  }

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $conpherence_id = $request->getURIData('id');
    if (!$conpherence_id) {
      return new Aphront404Response();
    }
    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withIDs(array($conpherence_id))
      ->needWidgetData(true)
      ->executeOne();
    if (!$conpherence) {
      return new Aphront404Response();
    }
    $this->setConpherence($conpherence);

    $this->setUserPreferences($user->loadPreferences());

    switch ($request->getStr('widget')) {
      case 'widgets-people':
        $content = $this->renderPeopleWidgetPaneContent();
        break;
      case 'widgets-settings':
        $content = $this->renderSettingsWidgetPaneContent();
        break;
      default:
        $widgets = $this->renderWidgetPaneContent();
        $content = $widgets;
        break;
    }
    return id(new AphrontAjaxResponse())->setContent($content);
  }

  private function renderWidgetPaneContent() {
    $conpherence = $this->getConpherence();

    $widgets = array();
    $new_icon = id(new PHUIIconView())
      ->setIcon('fa-plus')
      ->setHref($this->getWidgetURI())
      ->setMetadata(array('widget' => null))
      ->addSigil('conpherence-widget-adder');
    $header = javelin_tag(
      'a',
      array(
        'href' => '#',
        'sigil' => 'widgets-selector',
      ),
      pht('Participants'));

    $widgets[] = phutil_tag(
      'div',
      array(
        'class' => 'widgets-header',
      ),
      id(new PHUIHeaderView())
      ->setHeader($header)
      ->addActionItem($new_icon));
    $user = $this->getRequest()->getUser();
    // now the widget bodies
    $widgets[] = javelin_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-people',
        'sigil' => 'widgets-people',
      ),
      $this->renderPeopleWidgetPaneContent());
    $widgets[] = phutil_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-settings',
        'style' => 'display: none',
      ),
      $this->renderSettingsWidgetPaneContent());
    $widgets[] = phutil_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-edit',
        'style' => 'display: none',
      ));

    // without this implosion we get "," between each element in our widgets
    // array
    return array('widgets' => phutil_implode_html('', $widgets));
  }

  private function renderPeopleWidgetPaneContent() {
    return id(new ConpherencePeopleWidgetView())
      ->setUser($this->getViewer())
      ->setConpherence($this->getConpherence())
      ->setUpdateURI($this->getWidgetURI());
  }


  private function renderSettingsWidgetPaneContent() {
    $viewer = $this->getViewer();
    $conpherence = $this->getConpherence();
    $participant = $conpherence->getParticipantIfExists($viewer->getPHID());
    if (!$participant) {
      $can_join = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $conpherence,
        PhabricatorPolicyCapability::CAN_JOIN);
      if ($can_join) {
        $text = pht(
          'Notification settings are available after joining the room.');
      } else if ($viewer->isLoggedIn()) {
        $text = pht(
          'Notification settings not applicable to rooms you can not join.');
      } else {
        $text = pht(
          'Notification settings are available after logging in and joining '.
          'the room.');
      }
      return phutil_tag(
        'div',
        array(
          'class' => 'no-settings',
        ),
        $text);
    }
    $default = ConpherenceSettings::EMAIL_ALWAYS;
    $preference = $this->getUserPreferences();
    if ($preference) {
      $default = $preference->getPreference(
        PhabricatorUserPreferences::PREFERENCE_CONPH_NOTIFICATIONS,
        ConpherenceSettings::EMAIL_ALWAYS);
    }
    $settings = $participant->getSettings();
    $notifications = idx(
      $settings,
      'notifications',
      $default);
    $options = id(new AphrontFormRadioButtonControl())
      ->addButton(
        ConpherenceSettings::EMAIL_ALWAYS,
        ConpherenceSettings::getHumanString(
          ConpherenceSettings::EMAIL_ALWAYS),
        '')
      ->addButton(
        ConpherenceSettings::NOTIFICATIONS_ONLY,
        ConpherenceSettings::getHumanString(
          ConpherenceSettings::NOTIFICATIONS_ONLY),
        '')
      ->setName('notifications')
      ->setValue($notifications);

    $layout = array(
      $options,
      phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => 'action',
          'value' => 'notifications',
        )),
      phutil_tag(
        'button',
        array(
          'type' => 'submit',
          'class' => 'notifications-update',
        ),
        pht('Save')),
    );

    return phabricator_form(
      $viewer,
      array(
        'method' => 'POST',
        'action' => $this->getWidgetURI(),
        'sigil' => 'notifications-update',
      ),
      $layout);
  }

  private function getWidgetURI() {
    $conpherence = $this->getConpherence();
    return $this->getApplicationURI('update/'.$conpherence->getID().'/');
  }

}
