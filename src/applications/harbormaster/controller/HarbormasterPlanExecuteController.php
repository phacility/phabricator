<?php

final class HarbormasterPlanExecuteController
  extends HarbormasterPlanController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    $id = $this->id;

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    $cancel_uri = $this->getApplicationURI("plan/{$id}/");

    $v_buildable = null;
    $e_buildable = null;

    $errors = array();
    if ($request->isFormPost()) {
      $v_buildable = $request->getStr('buildable');

      if ($v_buildable) {
        $buildable = id(new HarbormasterBuildableQuery())
          ->setViewer($viewer)
          ->withIDs(array(trim($v_buildable, 'B')))
          ->executeOne();
        if (!$buildable) {
          $e_buildable = pht('Invalid');
        }
      } else {
        $e_buildable = pht('Required');
        $errors[] = pht('You must provide a buildable.');
      }

      if (!$errors) {
        $build_plan = HarbormasterBuild::initializeNewBuild($viewer)
          ->setBuildablePHID($buildable->getPHID())
          ->setBuildPlanPHID($plan->getPHID())
          ->save();

        $buildable_id = $buildable->getID();

        return id(new AphrontRedirectResponse())
          ->setURI("/B{$buildable_id}");
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())->setErrors($errors);
    }

    $form = id(new PHUIFormLayoutView())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Buildable'))
          ->setName('buildable')
          ->setValue($v_buildable)
          ->setError($e_buildable));

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Execute Build Plan'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($errors)
      ->appendChild($form)
      ->addSubmitButton(pht('Execute Build Plan'))
      ->addCancelButton($cancel_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
