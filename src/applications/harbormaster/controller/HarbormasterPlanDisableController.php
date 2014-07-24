<?php

final class HarbormasterPlanDisableController
  extends HarbormasterPlanController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      HarbormasterManagePlansCapability::CAPABILITY);

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    $plan_uri = $this->getApplicationURI('plan/'.$plan->getID().'/');

    if ($request->isFormPost()) {

      $type_status = HarbormasterBuildPlanTransaction::TYPE_STATUS;

      $v_status = $plan->isDisabled()
        ? HarbormasterBuildPlan::STATUS_ACTIVE
        : HarbormasterBuildPlan::STATUS_DISABLED;

      $xactions = array();

      $xactions[] = id(new HarbormasterBuildPlanTransaction())
        ->setTransactionType($type_status)
        ->setNewValue($v_status);

      $editor = id(new HarbormasterBuildPlanEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($plan, $xactions);

      return id(new AphrontRedirectResponse())->setURI($plan_uri);
    }

    if ($plan->isDisabled()) {
      $title = pht('Enable Build Plan');
      $body = pht('Enable this build plan?');
      $button = pht('Enable Plan');
    } else {
      $title = pht('Disable Build Plan');
      $body = pht(
        'Disable this build plan? It will no longer be executed '.
        'automatically.');
      $button = pht('Disable Plan');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->appendChild($body)
      ->addSubmitButton($button)
      ->addCancelButton($plan_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
