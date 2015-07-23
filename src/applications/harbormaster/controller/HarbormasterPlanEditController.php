<?php

final class HarbormasterPlanEditController extends HarbormasterPlanController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      HarbormasterManagePlansCapability::CAPABILITY);

    $id = $request->getURIData('id');
    if ($id) {
      $plan = id(new HarbormasterBuildPlanQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$plan) {
        return new Aphront404Response();
      }
    } else {
      $plan = HarbormasterBuildPlan::initializeNewBuildPlan($viewer);
    }

    $e_name = true;
    $v_name = $plan->getName();
    $validation_exception = null;
    if ($request->isFormPost()) {
      $xactions = array();

      $v_name = $request->getStr('name');
      $e_name = null;
      $type_name = HarbormasterBuildPlanTransaction::TYPE_NAME;

      $xactions[] = id(new HarbormasterBuildPlanTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $editor = id(new HarbormasterBuildPlanEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request);

      try {
        $editor->applyTransactions($plan, $xactions);
        return id(new AphrontRedirectResponse())
          ->setURI($this->getApplicationURI('plan/'.$plan->getID().'/'));
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $validation_exception->getShortMessage(
          HarbormasterBuildPlanTransaction::TYPE_NAME);
      }

    }

    $is_new = (!$plan->getID());
    if ($is_new) {
      $title = pht('New Build Plan');
      $cancel_uri = $this->getApplicationURI();
      $save_button = pht('Create Build Plan');
    } else {
      $id = $plan->getID();

      $title = pht('Edit Build Plan');
      $cancel_uri = $this->getApplicationURI('plan/'.$plan->getID().'/');
      $save_button = pht('Save Build Plan');
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Plan Name'))
          ->setName('name')
          ->setError($e_name)
          ->setValue($v_name));

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue($save_button)
        ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setValidationException($validation_exception)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_new) {
      $crumbs->addTextCrumb(pht('New Build Plan'));
    } else {
      $id = $plan->getID();
      $crumbs->addTextCrumb(
        pht('Plan %d', $id),
        $this->getApplicationURI("plan/{$id}/"));
      $crumbs->addTextCrumb(pht('Edit'));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }

}
