<?php

final class PhabricatorCalendarEventEditIconController
  extends PhabricatorCalendarController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    if ($this->id) {
      $event = id(new PhabricatorCalendarEventQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
          ->executeOne();
      if (!$event) {
        return new Aphront404Response();
      }
      $cancel_uri = $this->getApplicationURI('/E'.$event->getID());
      $event_icon = $event->getIcon();
    } else {
      $cancel_uri = '/calendar/';
      $event_icon = $request->getStr('value');
    }

    require_celerity_resource('calendar-icon-css');
    Javelin::initBehavior('phabricator-tooltips');

    $calendar_icons = PhabricatorCalendarIcon::getIconMap();

    if ($request->isFormPost()) {
      $v_icon = $request->getStr('icon');

      return id(new AphrontAjaxResponse())->setContent(
        array(
          'value' => $v_icon,
          'display' => PhabricatorCalendarIcon::renderIconForChooser($v_icon),
        ));
    }

    $ii = 0;
    $buttons = array();
    foreach ($calendar_icons as $icon => $label) {
      $view = id(new PHUIIconView())
        ->setIconFont($icon);

      $aural = javelin_tag(
        'span',
        array(
          'aural' => true,
        ),
        pht('Choose "%s" Icon', $label));

      if ($icon == $event_icon) {
        $class_extra = ' selected';
      } else {
        $class_extra = null;
      }

      $buttons[] = javelin_tag(
        'button',
        array(
          'class' => 'icon-button'.$class_extra,
          'name' => 'icon',
          'value' => $icon,
          'type' => 'submit',
          'sigil' => 'has-tooltip',
          'meta' => array(
            'tip' => $label,
          ),
        ),
        array(
          $aural,
          $view,
        ));
      if ((++$ii % 4) == 0) {
        $buttons[] = phutil_tag('br');
      }
    }

    $buttons = phutil_tag(
      'div',
      array(
        'class' => 'icon-grid',
      ),
      $buttons);

    return $this->newDialog()
      ->setTitle(pht('Choose Calendar Event Icon'))
      ->appendChild($buttons)
      ->addCancelButton($cancel_uri);
  }
}
