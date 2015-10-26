<?php

final class HarbormasterPlanDisableController
  extends HarbormasterPlanController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
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

    return $this->newDialog()
      ->setTitle($title)
      ->appendChild($body)
      ->addSubmitButton($button)
      ->addCancelButton($plan_uri);
  }

}
