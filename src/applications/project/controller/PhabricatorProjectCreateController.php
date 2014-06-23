<?php

final class PhabricatorProjectCreateController
  extends PhabricatorProjectController {


  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $this->requireApplicationCapability(
      ProjectCapabilityCreateProjects::CAPABILITY);

    $project = PhabricatorProject::initializeNewProject($user);

    $e_name = true;
    $type_name = PhabricatorProjectTransaction::TYPE_NAME;
    $v_name = $project->getName();
    $validation_exception = null;
    if ($request->isFormPost()) {
      $xactions = array();
      $v_name = $request->getStr('name');

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', PhabricatorEdgeConfig::TYPE_PROJ_MEMBER)
        ->setNewValue(
          array(
            '+' => array($user->getPHID() => $user->getPHID()),
          ));

      $editor = id(new PhabricatorProjectTransactionEditor())
        ->setActor($user)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request);
      try {
        $editor->applyTransactions($project, $xactions);
        if ($request->isAjax()) {
          return id(new AphrontAjaxResponse())
            ->setContent(array(
              'phid' => $project->getPHID(),
              'name' => $project->getName(),
            ));
        } else {
          return id(new AphrontRedirectResponse())
            ->setURI('/project/view/'.$project->getID().'/');
        }
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_name = $ex->getShortMessage($type_name);
      }
    }

    if ($request->isAjax()) {
      $form = new PHUIFormLayoutView();
    } else {
      $form = new AphrontFormView();
      $form->setUser($user);
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name));

    if ($request->isAjax()) {
      $errors = array();
      if ($validation_exception) {
        $errors = mpull($ex->getErrors(), 'getMessage');
      }
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setTitle(pht('Create a New Project'))
        ->setErrors($errors)
        ->appendChild($form)
        ->addSubmitButton(pht('Create Project'))
        ->addCancelButton('/project/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    } else {
      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Create'))
            ->addCancelButton('/project/'));

      $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
      $crumbs->addTextCrumb(
        pht('Create Project'),
        $this->getApplicationURI().'create/');

      $form_box = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Create New Project'))
        ->setValidationException($validation_exception)
        ->setForm($form);

      return $this->buildApplicationPage(
        array(
          $crumbs,
          $form_box,
        ),
        array(
          'title' => pht('Create New Project'),
        ));
    }
  }
}
