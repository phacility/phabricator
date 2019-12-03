<?php

final class PhabricatorApplicationTransactionValueController
  extends PhabricatorApplicationTransactionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $phid = $request->getURIData('phid');
    $type = $request->getURIData('value');

    $xaction = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$xaction) {
      return new Aphront404Response();
    }

    // For now, this pathway only supports policy transactions
    // to show the details of custom policies. If / when this pathway
    // supports more transaction types, rendering coding should be moved
    // into PhabricatorTransactions e.g. feed rendering code.

    // TODO: This should be some kind of "hey do you support this?" thing on
    // the transactions themselves.

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorRepositoryPushPolicyTransaction::TRANSACTIONTYPE:
      case PhabricatorApplicationPolicyChangeTransaction::TRANSACTIONTYPE:
        break;
      default:
        return new Aphront404Response();
        break;
    }

    if ($type == 'old') {
      $value = $xaction->getOldValue();
    } else {
      $value = $xaction->getNewValue();
    }

    $policy = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($value))
      ->executeOne();
    if (!$policy) {
      return new Aphront404Response();
    }

    if ($policy->getType() != PhabricatorPolicyType::TYPE_CUSTOM) {
      return new Aphront404Response();
    }

    $rules_view = id(new PhabricatorPolicyRulesView())
      ->setViewer($viewer)
      ->setPolicy($policy);

    $cancel_uri = $this->guessCancelURI($viewer, $xaction);

    return $this->newDialog()
      ->setTitle($policy->getFullName())
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($rules_view)
      ->addCancelButton($cancel_uri, pht('Close'));
  }
}
