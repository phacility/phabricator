<?php

final class PhabricatorProjectProfileEditController
  extends PhabricatorProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($user)
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

    $profile = $project->loadProfile();
    if (empty($profile)) {
      $profile = new PhabricatorProjectProfile();
    }

    $img_src = $profile->loadProfileImageURI();

    $options = PhabricatorProjectStatus::getStatusMap();

    $supported_formats = PhabricatorFile::getTransformableImageFormats();

    $e_name = true;
    $e_image = null;

    $errors = array();
    if ($request->isFormPost()) {
      try {
        $xactions = array();
        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransactionType::TYPE_NAME);
        $xaction->setNewValue($request->getStr('name'));
        $xactions[] = $xaction;

        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransactionType::TYPE_STATUS);
        $xaction->setNewValue($request->getStr('status'));
        $xactions[] = $xaction;

        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransactionType::TYPE_CAN_VIEW);
        $xaction->setNewValue($request->getStr('can_view'));
        $xactions[] = $xaction;

        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransactionType::TYPE_CAN_EDIT);
        $xaction->setNewValue($request->getStr('can_edit'));
        $xactions[] = $xaction;

        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransactionType::TYPE_CAN_JOIN);
        $xaction->setNewValue($request->getStr('can_join'));
        $xactions[] = $xaction;

        $editor = new PhabricatorProjectEditor($project);
        $editor->setActor($user);
        $editor->applyTransactions($xactions);
      } catch (PhabricatorProjectNameCollisionException $ex) {
        $e_name = 'Not Unique';
        $errors[] = $ex->getMessage();
      }

      $profile->setBlurb($request->getStr('blurb'));

      if (!strlen($project->getName())) {
        $e_name = pht('Required');
        $errors[] = pht('Project name is required.');
      } else {
        $e_name = null;
      }

      $default_image = $request->getExists('default_image');
      if ($default_image) {
        $profile->setProfileImagePHID(null);
      } else if (!empty($_FILES['image'])) {
        $err = idx($_FILES['image'], 'error');
        if ($err != UPLOAD_ERR_NO_FILE) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['image'],
            array(
              'authorPHID' => $user->getPHID(),
            ));
          $okay = $file->isTransformableImage();
          if ($okay) {
            $xformer = new PhabricatorImageTransformer();
            $xformed = $xformer->executeThumbTransform(
              $file,
              $x = 50,
              $y = 50);
            $profile->setProfileImagePHID($xformed->getPHID());
          } else {
            $e_image = pht('Not Supported');
            $errors[] =
              pht('This server only supports these image formats:').' '.
              implode(', ', $supported_formats).'.';
          }
        }
      }

      if (!$errors) {
        $project->save();
        $profile->setProjectPHID($project->getPHID());
        $profile->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/project/view/'.$project->getID().'/');
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle(pht('Form Errors'));
      $error_view->setErrors($errors);
    }

    $header_name = pht('Edit Project');
    $title = pht('Edit Project');
    $action = '/project/edit/'.$project->getID().'/';

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($project)
      ->execute();

    $form = new AphrontFormView();
    $form
      ->setID('project-edit-form')
      ->setUser($user)
      ->setAction($action)
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($project->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Project Status'))
          ->setName('status')
          ->setOptions($options)
          ->setValue($project->getStatus()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Blurb'))
          ->setName('blurb')
          ->setValue($profile->getBlurb()))
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>',
        pht(
          'NOTE: Policy settings are not yet fully implemented. '.
          'Some interfaces still ignore these settings, '.
          'particularly "Visible To".')))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setName('can_view')
          ->setCaption(pht('Members can always view a project.'))
          ->setPolicyObject($project)
          ->setPolicies($policies)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setName('can_edit')
          ->setPolicyObject($project)
          ->setPolicies($policies)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setName('can_join')
          ->setCaption(
            pht('Users who can edit a project can always join a project.'))
          ->setPolicyObject($project)
          ->setPolicies($policies)
          ->setCapability(PhabricatorPolicyCapability::CAN_JOIN))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Profile Image'))
          ->setValue(
            phutil_tag(
              'img',
              array(
                'src' => $img_src,
              ))))
      ->appendChild(
        id(new AphrontFormImageControl())
          ->setLabel(pht('Change Image'))
          ->setName('image')
          ->setError($e_image)
          ->setCaption(
            pht('Supported formats:').' '.implode(', ', $supported_formats)))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/project/view/'.$project->getID().'/')
          ->setValue(pht('Save')));

    $panel = new AphrontPanelView();
    $panel->setHeader($header_name);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setNoBackground();
    $panel->appendChild($form);

    $nav = $this->buildLocalNavigation($project);
    $nav->selectFilter('edit');
    $nav->appendChild(
      array(
        $error_view,
        $panel,
      ));

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($project->getName())
        ->setHref('/project/view/'.$project->getID().'/'));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit Project'))
        ->setHref($this->getApplicationURI()));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));
  }
}
