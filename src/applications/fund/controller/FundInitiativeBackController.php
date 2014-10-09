<?php

final class FundInitiativeBackController
  extends FundController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $initiative = id(new FundInitiativeQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$initiative) {
      return new Aphront404Response();
    }

    $merchant = id(new PhortuneMerchantQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($initiative->getMerchantPHID()))
      ->executeOne();
    if (!$merchant) {
      return new Aphront404Response();
    }

    $initiative_uri = '/'.$initiative->getMonogram();

    if ($initiative->isClosed()) {
      return $this->newDialog()
        ->setTitle(pht('Initiative Closed'))
        ->appendParagraph(
          pht('You can not back a closed initiative.'))
        ->addCancelButton($initiative_uri);
    }

    $accounts = PhortuneAccountQuery::loadAccountsForUser(
      $viewer,
      PhabricatorContentSource::newFromRequest($request));

    $v_amount = null;
    $e_amount = true;

    $v_account = head($accounts)->getPHID();

    $errors = array();
    if ($request->isFormPost()) {
      $v_amount = $request->getStr('amount');
      $v_account = $request->getStr('accountPHID');

      if (empty($accounts[$v_account])) {
        $errors[] = pht('You must specify an account.');
      } else {
        $account = $accounts[$v_account];
      }

      if (!strlen($v_amount)) {
        $errors[] = pht(
          'You must specify how much money you want to contribute to the '.
          'initiative.');
        $e_amount = pht('Required');
      } else {
        try {
          $currency = PhortuneCurrency::newFromUserInput(
            $viewer,
            $v_amount);
          $currency->assertInRange('1.00 USD', null);
        } catch (Exception $ex) {
          $errors[] = $ex->getMessage();
          $e_amount = pht('Invalid');
        }
      }

      if (!$errors) {
        $backer = FundBacker::initializeNewBacker($viewer)
          ->setInitiativePHID($initiative->getPHID())
          ->attachInitiative($initiative)
          ->setAmountAsCurrency($currency)
          ->save();

        $product = id(new PhortuneProductQuery())
          ->setViewer($viewer)
          ->withClassAndRef('FundBackerProduct', $initiative->getPHID())
          ->executeOne();

        $cart_implementation = id(new FundBackerCart())
          ->setInitiative($initiative);

        $cart = $account->newCart($viewer, $cart_implementation, $merchant);

        $purchase = $cart->newPurchase($viewer, $product);
        $purchase
          ->setBasePriceAsCurrency($currency)
          ->setMetadataValue('backerPHID', $backer->getPHID())
          ->save();

        $xactions = array();

        $xactions[] = id(new FundBackerTransaction())
          ->setTransactionType(FundBackerTransaction::TYPE_STATUS)
          ->setNewValue(FundBacker::STATUS_IN_CART);

        $editor = id(new FundBackerEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request);

        $editor->applyTransactions($backer, $xactions);

        $cart->activateCart();

        return id(new AphrontRedirectResponse())
          ->setURI($cart->getCheckoutURI());
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('accountPHID')
          ->setLabel(pht('Account'))
          ->setValue($v_account)
          ->setOptions(mpull($accounts, 'getName', 'getPHID')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('amount')
          ->setLabel(pht('Amount'))
          ->setValue($v_amount)
          ->setError($e_amount));

    return $this->newDialog()
      ->setTitle(
        pht('Back %s %s', $initiative->getMonogram(), $initiative->getName()))
      ->setErrors($errors)
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($initiative_uri)
      ->addSubmitButton(pht('Continue'));
  }

}
