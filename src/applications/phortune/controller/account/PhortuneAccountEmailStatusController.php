<?php

final class PhortuneAccountEmailStatusController
  extends PhortuneAccountController {

  protected function shouldRequireAccountEditCapability() {
    return true;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $account = $this->getAccount();

    $address = id(new PhortuneAccountEmailQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withIDs(array($request->getURIData('addressID')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$address) {
      return new Aphront404Response();
    }

    $address_uri = $address->getURI();

    $is_enable = false;
    $is_disable = false;

    $old_status = $address->getStatus();
    switch ($request->getURIData('action')) {
      case 'enable':
        if ($old_status === PhortuneAccountEmailStatus::STATUS_ACTIVE) {
          return $this->newDialog()
            ->setTitle(pht('Already Enabled'))
            ->appendParagraph(
              pht(
                'You can not enable this address because it is already '.
                'active.'))
            ->addCancelButton($address_uri);
        }

        if ($old_status === PhortuneAccountEmailStatus::STATUS_UNSUBSCRIBED) {
          return $this->newDialog()
            ->setTitle(pht('Permanently Unsubscribed'))
            ->appendParagraph(
              pht(
                'You can not enable this address because it has been '.
                'permanently unsubscribed.'))
            ->addCancelButton($address_uri);
        }

        $new_status = PhortuneAccountEmailStatus::STATUS_ACTIVE;
        $is_enable = true;
        break;
      case 'disable':
        if ($old_status === PhortuneAccountEmailStatus::STATUS_DISABLED) {
          return $this->newDialog()
            ->setTitle(pht('Already Disabled'))
            ->appendParagraph(
              pht(
                'You can not disabled this address because it is already '.
                'disabled.'))
            ->addCancelButton($address_uri);
        }

        if ($old_status === PhortuneAccountEmailStatus::STATUS_UNSUBSCRIBED) {
          return $this->newDialog()
            ->setTitle(pht('Permanently Unsubscribed'))
            ->appendParagraph(
              pht(
                'You can not disable this address because it has been '.
                'permanently unsubscribed.'))
            ->addCancelButton($address_uri);
        }

        $new_status = PhortuneAccountEmailStatus::STATUS_DISABLED;
        $is_disable = true;
        break;
      default:
        return new Aphront404Response();
    }

    if ($request->isFormOrHisecPost()) {
      $xactions = array();

      $xactions[] = $address->getApplicationTransactionTemplate()
        ->setTransactionType(
          PhortuneAccountEmailStatusTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_status);

      $address->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->setCancelURI($address_uri)
        ->applyTransactions($address, $xactions);

      return id(new AphrontRedirectResponse())->setURI($address_uri);
    }

    $dialog = $this->newDialog();

    $body = array();

    if ($is_disable) {
      $title = pht('Disable Address');

      $body[] = pht(
        'This address will no longer receive email, and access links will '.
        'no longer function.');

      $submit = pht('Disable Address');
    } else {
      $title = pht('Enable Address');

      $body[] = pht(
        'This address will receive email again, and existing links '.
        'to access order history will work again.');

      $submit = pht('Enable Address');
    }

    foreach ($body as $graph) {
      $dialog->appendParagraph($graph);
    }

    return $dialog
      ->setTitle($title)
      ->addCancelButton($address_uri)
      ->addSubmitButton($submit);
  }
}
