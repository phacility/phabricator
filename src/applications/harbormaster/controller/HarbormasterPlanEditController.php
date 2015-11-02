<?php

final class HarbormasterPlanEditController extends HarbormasterPlanController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

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
      $this->requireApplicationCapability(
        HarbormasterCreatePlansCapability::CAPABILITY);

      $plan = HarbormasterBuildPlan::initializeNewBuildPlan($viewer);
    }

    $e_name = true;
    $v_name = $plan->getName();
    $v_view = $plan->getViewPolicy();
    $v_edit = $plan->getEditPolicy();
    $validation_exception = null;
    if ($request->isFormPost()) {
      $xactions = array();

      $v_name = $request->getStr('name');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');

      $e_name = null;

      $type_name = HarbormasterBuildPlanTransaction::TYPE_NAME;
      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions[] = id(new HarbormasterBuildPlanTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new HarbormasterBuildPlanTransaction())
        ->setTransactionType($type_view)
        ->setNewValue($v_view);

      $xactions[] = id(new HarbormasterBuildPlanTransaction())
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

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
      $cancel_uri = $this->getApplicationURI('plan/');
      $save_button = pht('Create Build Plan');
    } else {
      $id = $plan->getID();

      $title = pht('Edit Build Plan');
      $cancel_uri = $this->getApplicationURI('plan/'.$plan->getID().'/');
      $save_button = pht('Save Build Plan');
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($plan)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Plan Name'))
          ->setName('name')
          ->setError($e_name)
          ->setValue($v_name))
      ->appendControl(
        id(new AphrontFormPolicyControl())
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicyObject($plan)
          ->setPolicies($policies)
          ->setValue($v_view)
          ->setName('viewPolicy'))
      ->appendControl(
        id(new AphrontFormPolicyControl())
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicyObject($plan)
          ->setPolicies($policies)
          ->setValue($v_edit)
          ->setName('editPolicy'))
      ->appendControl(
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
