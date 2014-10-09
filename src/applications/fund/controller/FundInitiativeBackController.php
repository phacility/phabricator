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

    $v_amount = null;
    $e_amount = true;
    $errors = array();
    if ($request->isFormPost()) {
      $v_amount = $request->getStr('amount');

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

        $account = PhortuneAccountQuery::loadActiveAccountForUser(
          $viewer,
          PhabricatorContentSource::newFromRequest($request));

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
