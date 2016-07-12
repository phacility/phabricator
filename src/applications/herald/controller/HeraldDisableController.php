<?php

final class HeraldDisableController extends HeraldController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');

    $rule = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$rule) {
      return new Aphront404Response();
    }

    if ($rule->isGlobalRule()) {
      $this->requireApplicationCapability(
        HeraldManageGlobalRulesCapability::CAPABILITY);
    }

    $view_uri = '/'.$rule->getMonogram();

    $is_disable = ($action === 'disable');

    if ($request->isFormPost()) {
      $xaction = id(new HeraldRuleTransaction())
        ->setTransactionType(HeraldRuleTransaction::TYPE_DISABLE)
        ->setNewValue($is_disable);

      id(new HeraldRuleEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($rule, array($xaction));

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($is_disable) {
      $title = pht('Really archive this rule?');
      $body = pht('This rule will no longer activate.');
      $button = pht('Archive Rule');
    } else {
      $title = pht('Really activate this rule?');
      $body = pht('This rule will become active again.');
      $button = pht('Activate Rule');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->appendChild($body)
      ->addSubmitButton($button)
      ->addCancelButton($view_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
