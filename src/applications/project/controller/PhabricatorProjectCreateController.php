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
    $errors = array();
    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_NAME)
        ->setNewValue($request->getStr('name'));

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
        ->setContentSourceFromRequest($request)
        ->applyTransactions($project, $xactions);

      // TODO: Deal with name collision exceptions more gracefully.

      if (!$errors) {
        $project->save();

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
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
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
          ->setValue($project->getName())
          ->setError($e_name));

    if ($request->isAjax()) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setTitle(pht('Create a New Project'))
        ->appendChild($error_view)
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
        ->setFormErrors($errors)
        ->setForm($form);

      return $this->buildApplicationPage(
        array(
          $crumbs,
          $form_box,
        ),
        array(
          'title' => pht('Create New Project'),
          'device' => true,
        ));
    }
  }
}
