<?php

final class PhabricatorProjectCreateController
  extends PhabricatorProjectController {


  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $project = new PhabricatorProject();
    $project->setAuthorPHID($user->getPHID());
    $profile = new PhabricatorProjectProfile();

    $e_name = true;
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
          PhabricatorProjectTransactionType::TYPE_MEMBERS);
        $xaction->setNewValue(array($user->getPHID()));
        $xactions[] = $xaction;

        $editor = new PhabricatorProjectEditor($project);
        $editor->setActor($user);
        $editor->applyTransactions($xactions);
      } catch (PhabricatorProjectNameCollisionException $ex) {
        $e_name = 'Not Unique';
        $errors[] = $ex->getMessage();
      }

      $profile->setBlurb($request->getStr('blurb'));

      if (!$errors) {
        $project->save();
        $profile->setProjectPHID($project->getPHID());
        $profile->save();

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
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    }

    if ($request->isAjax()) {
      $form = new AphrontFormLayoutView();
    } else {
      $form = new AphrontFormView();
      $form->setUser($user);
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($project->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Blurb')
          ->setName('blurb')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setValue($profile->getBlurb()));

    if ($request->isAjax()) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setTitle('Create a New Project')
        ->appendChild($error_view)
        ->appendChild($form)
        ->addSubmitButton('Create Project')
        ->addCancelButton('/project/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    } else {

      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Create')
            ->addCancelButton('/project/'));

      $panel = new AphrontPanelView();
      $panel
        ->setWidth(AphrontPanelView::WIDTH_FORM)
        ->setHeader('Create a New Project')
        ->appendChild($form);

      return $this->buildStandardPageResponse(
        array(
          $error_view,
          $panel,
        ),
        array(
          'title' => 'Create new Project',
        ));
    }
  }
}
