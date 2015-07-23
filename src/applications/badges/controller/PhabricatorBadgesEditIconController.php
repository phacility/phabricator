<?php

final class PhabricatorBadgesEditIconController
  extends PhabricatorBadgesController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $badge = id(new PhabricatorBadgesQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
          ->executeOne();
      if (!$badge) {
        return new Aphront404Response();
      }
      $cancel_uri =
        $this->getApplicationURI('view/'.$badge->getID().'/');
      $badge_icon = $badge->getIcon();
    } else {
      $this->requireApplicationCapability(
        PhabricatorBadgesCreateCapability::CAPABILITY);

      $cancel_uri = '/badges/';
      $badge_icon = $request->getStr('value');
    }

    require_celerity_resource('project-icon-css');
    Javelin::initBehavior('phabricator-tooltips');

    $badge_icons = PhabricatorBadgesIcon::getIconMap();

    if ($request->isFormPost()) {
      $v_icon = $request->getStr('icon');

      return id(new AphrontAjaxResponse())->setContent(
        array(
          'value' => $v_icon,
          'display' => PhabricatorBadgesIcon::renderIconForChooser($v_icon),
        ));
    }

    $ii = 0;
    $buttons = array();
    foreach ($badge_icons as $icon => $label) {
      $view = id(new PHUIIconView())
        ->setIconFont($icon);

      $aural = javelin_tag(
        'span',
        array(
          'aural' => true,
        ),
        pht('Choose "%s" Icon', $label));

      if ($icon == $badge_icon) {
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
      ->setTitle(pht('Choose Badge Icon'))
      ->appendChild($buttons)
      ->addCancelButton($cancel_uri);
  }
}
