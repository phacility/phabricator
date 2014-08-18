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

  protected function buildChargesTable(array $charges, $show_cart = true) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $rows = array();
    foreach ($charges as $charge) {
      $cart = $charge->getCart();
      $cart_id = $cart->getID();
      $cart_uri = $this->getApplicationURI("cart/{$cart_id}/");
      $cart_href = phutil_tag(
        'a',
        array(
          'href' => $cart_uri,
        ),
        pht('Cart %d', $cart_id));

      $rows[] = array(
        $charge->getID(),
        $cart_href,
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
          pht('ID'),
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
          'strong',
          '',
          '',
          'wide right',
          '',
          '',
        ))
      ->setColumnVisibility(
        array(
          true,
          $show_cart,
        ));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Charge History'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($charge_table);
  }

  protected function addAccountCrumb(
    $crumbs,
    PhortuneAccount $account,
    $link = true) {

    $name = pht('Account');
    $href = null;

    if ($link) {
      $href = $this->getApplicationURI($account->getID().'/');
      $crumbs->addTextCrumb($name, $href);
    } else {
      $crumbs->addTextCrumb($name);
    }
  }

}
