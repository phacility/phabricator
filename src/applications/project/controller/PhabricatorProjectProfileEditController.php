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
      ->needProfiles(true)
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $profile = $project->getProfile();
    $options = PhabricatorProjectStatus::getStatusMap();

    $e_name = true;

    $errors = array();
    if ($request->isFormPost()) {
      try {
        $xactions = array();
        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransaction::TYPE_NAME);
        $xaction->setNewValue($request->getStr('name'));
        $xactions[] = $xaction;

        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransaction::TYPE_STATUS);
        $xaction->setNewValue($request->getStr('status'));
        $xactions[] = $xaction;

        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorTransactions::TYPE_VIEW_POLICY);
        $xaction->setNewValue($request->getStr('can_view'));
        $xactions[] = $xaction;

        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorTransactions::TYPE_EDIT_POLICY);
        $xaction->setNewValue($request->getStr('can_edit'));
        $xactions[] = $xaction;

        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorTransactions::TYPE_JOIN_POLICY);
        $xaction->setNewValue($request->getStr('can_join'));
        $xactions[] = $xaction;

        $editor = new PhabricatorProjectEditor($project);
        $editor->setActor($user);
        $editor->applyTransactions($xactions);
      } catch (PhabricatorProjectNameCollisionException $ex) {
        $e_name = pht('Not Unique');
        $errors[] = $ex->getMessage();
      }

      $profile->setBlurb($request->getStr('blurb'));

      if (!strlen($project->getName())) {
        $e_name = pht('Required');
        $errors[] = pht('Project name is required.');
      } else {
        $e_name = null;
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
        id(new PhabricatorRemarkupControl())
          ->setLabel(pht('Description'))
          ->setName('blurb')
          ->setValue($profile->getBlurb()))
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
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/project/view/'.$project->getID().'/')
          ->setValue(pht('Save')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormError($error_view)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView())
      ->addTextCrumb(
        $project->getName(),
        '/project/view/'.$project->getID().'/')
      ->addTextCrumb(pht('Edit Project'), $this->getApplicationURI());

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }
}
