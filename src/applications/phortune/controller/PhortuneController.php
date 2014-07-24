<?php

abstract class PhortuneController extends PhabricatorController {

  protected function loadActiveAccount(PhabricatorUser $user) {
    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($user)
      ->withMemberPHIDs(array($user->getPHID()))
      ->execute();

    if (!$accounts) {
      return $this->createUserAccount($user);
    } else if (count($accounts) == 1) {
      return head($accounts);
    } else {
      throw new Exception('TODO: No account selection yet.');
    }
  }

  protected function createUserAccount(PhabricatorUser $user) {
    $request = $this->getRequest();

    $xactions = array();
    $xactions[] = id(new PhortuneAccountTransaction())
      ->setTransactionType(PhortuneAccountTransaction::TYPE_NAME)
      ->setNewValue(pht('Account (%s)', $user->getUserName()));

    $xactions[] = id(new PhortuneAccountTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        PhabricatorEdgeConfig::TYPE_ACCOUNT_HAS_MEMBER)
      ->setNewValue(
        array(
          '=' => array($user->getPHID() => $user->getPHID()),
        ));

    $account = id(new PhortuneAccount())
      ->attachMemberPHIDs(array());

    $editor = id(new PhortuneAccountEditor())
      ->setActor($user)
      ->setContentSourceFromRequest($request);

    // We create an account for you the first time you visit Phortune.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      $editor->applyTransactions($account, $xactions);

    unset($unguarded);

    return $account;
  }

  protected function buildChargesTable(array $charges) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $rows = array();
    foreach ($charges as $charge) {
      $rows[] = array(
        $charge->getID(),
        $charge->getCartPHID(),
        $charge->getPaymentProviderKey(),
        $charge->getPaymentMethodPHID(),
        PhortuneCurrency::newFromUSDCents($charge->getAmountInCents())
          ->formatForDisplay(),
        $charge->getStatus(),
        phabricator_datetime($charge->getDateCreated(), $viewer),
      );
    }

    $charge_table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Charge ID'),
          pht('Cart'),
          pht('Provider'),
          pht('Method'),
          pht('Amount'),
          pht('Status'),
          pht('Created'),
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          '',
          '',
          'wide right',
          '',
          '',
        ));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Charge History'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($charge_table);
  }

}
