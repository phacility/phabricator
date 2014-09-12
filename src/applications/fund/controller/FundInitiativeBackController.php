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
        } catch (Exception $ex) {
          $errors[] = $ex->getMessage();
          $e_amount = pht('Invalid');
        }
      }

      if (!$errors) {
        $backer = FundBacker::initializeNewBacker($viewer)
          ->setInitiativePHID($initiative->getPHID())
          ->attachInitiative($initiative)
          ->setAmountInCents($currency->getValue())
          ->save();

        // TODO: Here, we'd create a purchase and cart.

        $xactions = array();

        $xactions[] = id(new FundBackerTransaction())
          ->setTransactionType(FundBackerTransaction::TYPE_STATUS)
          ->setNewValue(FundBacker::STATUS_IN_CART);

        $editor = id(new FundBackerEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request);

        $editor->applyTransactions($backer, $xactions);

        // TODO: Here, we'd ship the user into Phortune.

        return id(new AphrontRedirectResponse())->setURI($initiative_uri);
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
      ->setTitle(pht('Back Initiative'))
      ->setErrors($errors)
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($initiative_uri)
      ->addSubmitButton(pht('Continue'));
  }

}
