<?php

final class PhabricatorProjectEditPictureController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needImages(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $this->setProject($project);

    $edit_uri = $this->getApplicationURI('profile/'.$project->getID().'/');
    $view_uri = $this->getApplicationURI('profile/'.$project->getID().'/');

    $supported_formats = PhabricatorFile::getTransformableImageFormats();
    $e_file = true;
    $errors = array();

    if ($request->isFormPost()) {
      $phid = $request->getStr('phid');
      $is_default = false;
      if ($phid == PhabricatorPHIDConstants::PHID_VOID) {
        $phid = null;
        $is_default = true;
      } else if ($phid) {
        $file = id(new PhabricatorFileQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($phid))
          ->executeOne();
      } else {
        if ($request->getFileExists('picture')) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['picture'],
            array(
              'authorPHID' => $viewer->getPHID(),
              'canCDN' => true,
            ));
        } else {
          $e_file = pht('Required');
          $errors[] = pht(
            'You must choose a file when uploading a new project picture.');
        }
      }

      if (!$errors && !$is_default) {
        if (!$file->isTransformableImage()) {
          $e_file = pht('Not Supported');
          $errors[] = pht(
            'This server only supports these image formats: %s.',
            implode(', ', $supported_formats));
        } else {
          $xform = PhabricatorFileTransform::getTransformByKey(
            PhabricatorFileThumbnailTransform::TRANSFORM_PROFILE);
          $xformed = $xform->executeTransform($file);
        }
      }

      if (!$errors) {
        if ($is_default) {
          $new_value = null;
        } else {
          $new_value = $xformed->getPHID();
        }

        $xactions = array();
        $xactions[] = id(new PhabricatorProjectTransaction())
          ->setTransactionType(PhabricatorProjectTransaction::TYPE_IMAGE)
          ->setNewValue($new_value);

        $editor = id(new PhabricatorProjectTransactionEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnMissingFields(true)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($project, $xactions);

        return id(new AphrontRedirectResponse())->setURI($edit_uri);
      }
    }

    $title = pht('Edit Project Picture');

    $form = id(new PHUIFormLayoutView())
      ->setUser($viewer);

    $default_image = PhabricatorFile::loadBuiltin($viewer, 'project.png');

    $images = array();

    $current = $project->getProfileImagePHID();
    $has_current = false;
    if ($current) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($current))
        ->execute();
      if ($files) {
        $file = head($files);
        if ($file->isTransformableImage()) {
          $has_current = true;
          $images[$current] = array(
            'uri' => $file->getBestURI(),
            'tip' => pht('Current Picture'),
          );
        }
      }
    }

    $images[PhabricatorPHIDConstants::PHID_VOID] = array(
      'uri' => $default_image->getBestURI(),
      'tip' => pht('No Picture'),
    );

    require_celerity_resource('people-profile-css');
    Javelin::initBehavior('phabricator-tooltips', array());

    $buttons = array();
    foreach ($images as $phid => $spec) {
      $button = javelin_tag(
        'button',
        array(
          'class' => 'grey profile-image-button',
          'sigil' => 'has-tooltip',
          'meta' => array(
            'tip' => $spec['tip'],
            'size' => 300,
          ),
        ),
        phutil_tag(
          'img',
          array(
            'height' => 50,
            'width' => 50,
            'src' => $spec['uri'],
          )));

      $button = array(
        phutil_tag(
          'input',
          array(
            'type'  => 'hidden',
            'name'  => 'phid',
            'value' => $phid,
          )),
        $button,
      );

      $button = phabricator_form(
        $viewer,
        array(
          'class' => 'profile-image-form',
          'method' => 'POST',
        ),
        $button);

      $buttons[] = $button;
    }

    if ($has_current) {
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Current Picture'))
          ->setValue(array_shift($buttons)));
    }

    $form->appendChild(
      id(new AphrontFormMarkupControl())
        ->setLabel(pht('Use Picture'))
        ->setValue(
          array(
            $this->renderDefaultForm($project),
            $buttons,
          )));

    $launch_id = celerity_generate_unique_node_id();
    $input_id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'launch-icon-composer',
      array(
        'launchID' => $launch_id,
        'inputID' => $input_id,
      ));

    $compose_button = javelin_tag(
      'button',
      array(
        'class' => 'grey',
        'id' => $launch_id,
        'sigil' => 'icon-composer',
      ),
      pht('Choose Icon and Color...'));

    $compose_input = javelin_tag(
      'input',
      array(
        'type' => 'hidden',
        'id' => $input_id,
        'name' => 'phid',
      ));

    $compose_form = phabricator_form(
      $viewer,
      array(
        'class' => 'profile-image-form',
        'method' => 'POST',
      ),
      array(
        $compose_input,
        $compose_button,
      ));

    $form->appendChild(
      id(new AphrontFormMarkupControl())
        ->setLabel(pht('Quick Create'))
        ->setValue($compose_form));

    $upload_form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setName('picture')
          ->setLabel(pht('Upload Picture'))
          ->setError($e_file)
          ->setCaption(
            pht('Supported formats: %s', implode(', ', $supported_formats))))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($edit_uri)
          ->setValue(pht('Upload Picture')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    $upload_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Upload New Picture'))
      ->setForm($upload_form);

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorProject::ITEM_MANAGE);

    return $this->newPage()
      ->setTitle($title)
      ->setNavigation($nav)
      ->appendChild(
        array(
          $form_box,
          $upload_box,
        ));
  }

  private function renderDefaultForm(PhabricatorProject $project) {
    $viewer = $this->getViewer();
    $compose_color = $project->getDisplayIconComposeColor();
    $compose_icon = $project->getDisplayIconComposeIcon();

    $default_builtin = id(new PhabricatorFilesComposeIconBuiltinFile())
      ->setColor($compose_color)
      ->setIcon($compose_icon);

    $file_builtins = PhabricatorFile::loadBuiltins(
      $viewer,
      array($default_builtin));

    $file_builtin = head($file_builtins);

    $default_button = javelin_tag(
      'button',
      array(
        'class' => 'grey profile-image-button',
        'sigil' => 'has-tooltip',
        'meta' => array(
          'tip' => pht('Use Icon and Color'),
          'size' => 300,
        ),
      ),
      phutil_tag(
        'img',
        array(
          'height' => 50,
          'width' => 50,
          'src' => $file_builtin->getBestURI(),
        )));

    $inputs = array(
      'projectPHID' => $project->getPHID(),
      'icon' => $compose_icon,
      'color' => $compose_color,
    );

    foreach ($inputs as $key => $value) {
      $inputs[$key] = javelin_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $key,
          'value' => $value,
        ));
    }

    $default_form = phabricator_form(
      $viewer,
      array(
        'class' => 'profile-image-form',
        'method' => 'POST',
        'action' => '/file/compose/',
       ),
      array(
        $inputs,
        $default_button,
      ));

    return $default_form;
  }

}
