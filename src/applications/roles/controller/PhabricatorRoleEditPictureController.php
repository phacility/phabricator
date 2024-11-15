<?php

final class PhabricatorRoleEditPictureController
  extends PhabricatorRoleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needImages(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }

    $this->setRole($role);

    $manage_uri = $this->getApplicationURI('manage/'.$role->getID().'/');

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
            'You must choose a file when uploading a new role picture.');
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
        $xactions[] = id(new PhabricatorRoleTransaction())
          ->setTransactionType(
              PhabricatorRoleImageTransaction::TRANSACTIONTYPE)
          ->setNewValue($new_value);

        $editor = id(new PhabricatorRoleTransactionEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnMissingFields(true)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($role, $xactions);

        return id(new AphrontRedirectResponse())->setURI($manage_uri);
      }
    }

    $title = pht('Edit Role Picture');

    $form = id(new PHUIFormLayoutView())
      ->setUser($viewer);

    $builtin = PhabricatorRoleIconSet::getIconImage(
      $role->getIcon());
    $default_image = PhabricatorFile::loadBuiltin($this->getViewer(),
      'roles/'.$builtin);

    $images = array();

    $current = $role->getProfileImagePHID();
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

    $root = dirname(phutil_get_library_root('phabricator'));
    $root = $root.'/resources/builtin/roles/v3/';

    $builtins = id(new FileFinder($root))
      ->withType('f')
      ->withFollowSymlinks(true)
      ->find();

    foreach ($builtins as $builtin) {
      $file = PhabricatorFile::loadBuiltin($viewer, 'roles/v3/'.$builtin);
      $images[$file->getPHID()] = array(
        'uri' => $file->getBestURI(),
        'tip' => pht('Builtin Image'),
      );
    }

    $images[PhabricatorPHIDConstants::PHID_VOID] = array(
      'uri' => $default_image->getBestURI(),
      'tip' => pht('Default Picture'),
    );

    require_celerity_resource('people-profile-css');
    Javelin::initBehavior('phabricator-tooltips', array());

    $buttons = array();
    foreach ($images as $phid => $spec) {
      $button = javelin_tag(
        'button',
        array(
          'class' => 'button-grey profile-image-button',
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
            $this->renderDefaultForm($role),
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
        'class' => 'button-grey',
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
        ->setLabel(pht('Custom'))
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
          ->addCancelButton($manage_uri)
          ->setValue(pht('Upload Picture')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    $upload_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Upload New Picture'))
      ->setForm($upload_form);

    $nav = $this->newNavigation(
      $role,
      PhabricatorRole::ITEM_MANAGE);

    return $this->newPage()
      ->setTitle($title)
      ->setNavigation($nav)
      ->appendChild(
        array(
          $form_box,
          $upload_box,
        ));
  }

  private function renderDefaultForm(PhabricatorRole $role) {
    $viewer = $this->getViewer();
    $compose_color = $role->getDisplayIconComposeColor();
    $compose_icon = $role->getDisplayIconComposeIcon();

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
        'class' => 'button-grey profile-image-button',
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
      'rolePHID' => $role->getPHID(),
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
