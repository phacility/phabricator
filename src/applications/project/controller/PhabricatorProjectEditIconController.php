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
      $xactions = array();

      $v_icon = $request->getStr('icon');
      $v_icon_color = $request->getStr('color');

      $type_icon = PhabricatorProjectTransaction::TYPE_ICON;
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType($type_icon)
        ->setNewValue($v_icon);

      $type_icon_color = PhabricatorProjectTransaction::TYPE_COLOR;
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType($type_icon_color)
        ->setNewValue($v_icon_color);

      $editor = id(new PhabricatorProjectTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true);

      $editor->applyTransactions($project, $xactions);

      return id(new AphrontReloadResponse())->setURI($edit_uri);
    }

    $shades = PHUITagView::getShadeMap();
    $shades = array_select_keys(
      $shades,
      array(PhabricatorProject::DEFAULT_COLOR)) + $shades;
    unset($shades[PHUITagView::COLOR_DISABLED]);

    $color_form = id(new AphrontFormView())
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Color'))
          ->setName('color')
          ->setValue($project->getColor())
          ->setOptions($shades));

    require_celerity_resource('project-icon-css');
    Javelin::initBehavior('phabricator-tooltips');

    $project_icons = PhabricatorProjectIcon::getIconMap();
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

    $color_form->appendChild(
      id(new AphrontFormMarkupControl())
        ->setLabel(pht('Icon'))
        ->setValue($buttons));

    return $this->newDialog()
      ->setTitle(pht('Choose Project Icon'))
      ->appendChild($color_form->buildLayoutView())
      ->addCancelButton($edit_uri);
  }
}
