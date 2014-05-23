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

    $view_uri = '/tag/'.$project->getPrimarySlug().'/';
    $edit_uri = $this->getApplicationURI('edit/'.$project->getID().'/');

    if ($request->isFormPost()) {
      $v_icon = $request->getStr('icon');
      $type_icon = PhabricatorProjectTransaction::TYPE_ICON;
      $xactions = array(id(new PhabricatorProjectTransaction())
        ->setTransactionType($type_icon)
        ->setNewValue($v_icon));

      $editor = id(new PhabricatorProjectTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true);

      $editor->applyTransactions($project, $xactions);

      return id(new AphrontReloadResponse())->setURI($edit_uri);
    }

    require_celerity_resource('project-icon-css');
    Javelin::initBehavior('phabricator-tooltips');

    $project_icons = PhabricatorProjectIcon::getIconMap();
    $ii = 0;
    $buttons = array();
    foreach ($project_icons as $icon => $label) {
      $view = id(new PHUIIconView())
        ->setIconFont($icon.' bluegrey');

      $aural = javelin_tag(
        'span',
        array(
          'aural' => true,
        ),
        pht('Choose "%s" Icon', $label));

      if ($icon == $project->getIcon()) {
        $class_extra = ' selected';
        $tip = $label . pht(' - selected');
      } else {
        $class_extra = null;
        $tip = $label;
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
            'tip' => $tip,
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

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Choose Project Icon'))
      ->appendChild($buttons)
      ->addCancelButton($edit_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
