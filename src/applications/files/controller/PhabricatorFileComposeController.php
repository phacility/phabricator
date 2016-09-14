<?php

final class PhabricatorFileComposeController
  extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $color_map = PhabricatorFilesComposeIconBuiltinFile::getAllColors();
    $icon_map = $this->getIconMap();

    if ($request->isFormPost()) {
      $project_phid = $request->getStr('projectPHID');
      if ($project_phid) {
        $project = id(new PhabricatorProjectQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($project_phid))
          ->requireCapabilities(
            array(
              PhabricatorPolicyCapability::CAN_VIEW,
              PhabricatorPolicyCapability::CAN_EDIT,
            ))
          ->executeOne();
        if (!$project) {
          return new Aphront404Response();
        }
      }

      $icon = $request->getStr('icon');
      $color = $request->getStr('color');

      $composer = id(new PhabricatorFilesComposeIconBuiltinFile())
        ->setIcon($icon)
        ->setColor($color);

      $data = $composer->loadBuiltinFileData();

      $file = PhabricatorFile::newFromFileData(
        $data,
        array(
          'name' => $composer->getBuiltinDisplayName(),
          'profile' => true,
          'canCDN' => true,
        ));

      if ($project_phid) {
        $edit_uri = '/project/manage/'.$project->getID().'/';

        $xactions = array();
        $xactions[] = id(new PhabricatorProjectTransaction())
          ->setTransactionType(PhabricatorProjectTransaction::TYPE_IMAGE)
          ->setNewValue($file->getPHID());

        $editor = id(new PhabricatorProjectTransactionEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnMissingFields(true)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($project, $xactions);

        return id(new AphrontRedirectResponse())->setURI($edit_uri);
      } else {
        $content = array(
          'phid' => $file->getPHID(),
        );

        return id(new AphrontAjaxResponse())->setContent($content);
      }
    }

    $value_color = head_key($color_map);
    $value_icon = head_key($icon_map);

    require_celerity_resource('people-profile-css');

    $buttons = array();
    foreach ($color_map as $color => $info) {
      $quip = idx($info, 'quip');

      $buttons[] = javelin_tag(
        'button',
        array(
          'class' => 'grey profile-image-button',
          'sigil' => 'has-tooltip compose-select-color',
          'style' => 'margin: 0 8px 8px 0',
          'meta' => array(
            'color' => $color,
            'tip' => $quip,
          ),
        ),
        id(new PHUIIconView())
          ->addClass('compose-background-'.$color));
    }


    $icons = array();
    foreach ($icon_map as $icon => $spec) {
      $quip = idx($spec, 'quip');

      $icons[] = javelin_tag(
        'button',
        array(
          'class' => 'grey profile-image-button',
          'sigil' => 'has-tooltip compose-select-icon',
          'style' => 'margin: 0 8px 8px 0',
          'meta' => array(
            'icon' => $icon,
            'tip' => $quip,
          ),
        ),
        id(new PHUIIconView())
          ->setIcon($icon)
          ->addClass('compose-icon-bg'));
    }

    $dialog_id = celerity_generate_unique_node_id();
    $color_input_id = celerity_generate_unique_node_id();
    $icon_input_id = celerity_generate_unique_node_id();
    $preview_id = celerity_generate_unique_node_id();

    $preview = id(new PHUIIconView())
      ->setID($preview_id)
      ->addClass('compose-background-'.$value_color)
      ->setIcon($value_icon)
      ->addClass('compose-icon-bg');

    $color_input = javelin_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'color',
        'value' => $value_color,
        'id' => $color_input_id,
      ));

    $icon_input = javelin_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'icon',
        'value' => $value_icon,
        'id' => $icon_input_id,
      ));

    Javelin::initBehavior('phabricator-tooltips');
    Javelin::initBehavior(
      'icon-composer',
      array(
        'dialogID' => $dialog_id,
        'colorInputID' => $color_input_id,
        'iconInputID' => $icon_input_id,
        'previewID' => $preview_id,
        'defaultColor' => $value_color,
        'defaultIcon' => $value_icon,
      ));

    return $this->newDialog()
      ->setFormID($dialog_id)
      ->setClass('compose-dialog')
      ->setTitle(pht('Compose Image'))
      ->appendChild(
        phutil_tag(
          'div',
          array(
            'class' => 'compose-header',
          ),
          pht('Choose Background Color')))
      ->appendChild($buttons)
      ->appendChild(
        phutil_tag(
          'div',
          array(
            'class' => 'compose-header',
          ),
          pht('Choose Icon')))
      ->appendChild($icons)
      ->appendChild(
        phutil_tag(
          'div',
          array(
            'class' => 'compose-header',
          ),
          pht('Preview')))
      ->appendChild($preview)
      ->appendChild($color_input)
      ->appendChild($icon_input)
      ->addCancelButton('/')
      ->addSubmitButton(pht('Save Image'));
  }

  private function getIconMap() {
    $icon_map = PhabricatorFilesComposeIconBuiltinFile::getAllIcons();

    $first = array(
      'fa-briefcase',
      'fa-tags',
      'fa-folder',
      'fa-group',
      'fa-bug',
      'fa-trash-o',
      'fa-calendar',
      'fa-flag-checkered',
      'fa-envelope',
      'fa-truck',
      'fa-lock',
      'fa-umbrella',
      'fa-cloud',
      'fa-building',
      'fa-credit-card',
      'fa-flask',
    );

    $icon_map = array_select_keys($icon_map, $first) + $icon_map;

    return $icon_map;
  }

}
