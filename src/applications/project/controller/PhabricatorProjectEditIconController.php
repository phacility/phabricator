<?php

final class PhabricatorProjectEditIconController
  extends PhabricatorProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $edit_uri = $this->getApplicationURI('edit/'.$project->getID().'/');
    require_celerity_resource('project-icon-css');
    Javelin::initBehavior('phabricator-tooltips');

    $project_icons = PhabricatorProjectIcon::getIconMap();

    if ($request->isFormPost()) {
      $v_icon = $request->getStr('icon');

      return id(new AphrontAjaxResponse())->setContent(
        array(
          'value' => $v_icon,
          'display' => PhabricatorProjectIcon::renderIconForChooser($v_icon),
        ));
    }

    $ii = 0;
    $buttons = array();
    foreach ($project_icons as $icon => $label) {
      $view = id(new PHUIIconView())
        ->setIconFont($icon);

      $aural = javelin_tag(
        'span',
        array(
          'aural' => true,
        ),
        pht('Choose "%s" Icon', $label));

      if ($icon == $project->getIcon()) {
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
          )
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
      ->setTitle(pht('Choose Project Icon'))
      ->appendChild($buttons)
      ->addCancelButton($edit_uri);
  }
}
