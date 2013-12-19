<?php

final class PhabricatorProjectProfilePictureController
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
      ->needProfiles(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $project_uri = $this->getApplicationURI('view/'.$project->getID().'/');

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
          $xformer = new PhabricatorImageTransformer();
          $xformed = $xformer->executeProfileTransform(
            $file,
            $width = 50,
            $min_height = 50,
            $max_height = 50);
        }
      }

      if (!$errors) {
        $profile = $project->getProfile();
        if ($is_default) {
          $profile->setProfileImagePHID(null);
        } else {
          $profile->setProfileImagePHID($xformed->getPHID());
          $xformed->attachToObject($viewer, $project->getPHID());
        }
        $profile->save();
        return id(new AphrontRedirectResponse())->setURI($project_uri);
      }
    }

    $title = pht('Edit Project Picture');
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($project->getName(), $project_uri);
    $crumbs->addTextCrumb($title);

    $form = id(new PHUIFormLayoutView())
      ->setUser($viewer);

    $default_image = PhabricatorFile::loadBuiltin($viewer, 'project.png');

    $images = array();

    $current = $project->getProfile()->getProfileImagePHID();
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
      'tip' => pht('Default Picture'),
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
        $button);

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
        ->setValue($buttons));

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

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormError($errors)
      ->setForm($form);

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
          ->addCancelButton($project_uri)
          ->setValue(pht('Upload Picture')));

    if ($errors) {
      $errors = id(new AphrontErrorView())->setErrors($errors);
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormError($errors)
      ->setForm($form);

    $upload_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Upload New Picture'))
      ->setForm($upload_form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $upload_box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }
}
