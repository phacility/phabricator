<?php

final class HarbormasterPlanBehaviorController
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

    $behavior_key = $request->getURIData('behaviorKey');
    $metadata_key = HarbormasterBuildPlanBehavior::getTransactionMetadataKey();

    $behaviors = HarbormasterBuildPlanBehavior::newPlanBehaviors();
    $behavior = idx($behaviors, $behavior_key);
    if (!$behavior) {
      return new Aphront404Response();
    }

    $plan_uri = $plan->getURI();

    $v_option = $behavior->getPlanOption($plan)->getKey();
    if ($request->isFormPost()) {
      $v_option = $request->getStr('option');

      $xactions = array();

      $xactions[] = id(new HarbormasterBuildPlanTransaction())
        ->setTransactionType(
          HarbormasterBuildPlanBehaviorTransaction::TRANSACTIONTYPE)
        ->setMetadataValue($metadata_key, $behavior_key)
        ->setNewValue($v_option);

      $editor = id(new HarbormasterBuildPlanEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($plan, $xactions);

      return id(new AphrontRedirectResponse())->setURI($plan_uri);
    }

    $select_control = id(new AphrontFormRadioButtonControl())
      ->setName('option')
      ->setValue($v_option)
      ->setLabel(pht('Option'));

    foreach ($behavior->getOptions() as $option) {
      $icon = id(new PHUIIconView())
        ->setIcon($option->getIcon());

      $select_control->addButton(
        $option->getKey(),
        array(
          $icon,
          ' ',
          $option->getName(),
        ),
        $option->getDescription());
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendInstructions(
        pht(
          'Choose a build plan behavior for "%s".',
          phutil_tag('strong', array(), $behavior->getName())))
      ->appendRemarkupInstructions($behavior->getEditInstructions())
      ->appendControl($select_control);

    return $this->newDialog()
      ->setTitle(pht('Edit Behavior: %s', $behavior->getName()))
      ->appendForm($form)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addSubmitButton(pht('Save Changes'))
      ->addCancelButton($plan_uri);
  }

}
