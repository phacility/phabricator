<?php

final class PhortuneMerchantInvoiceCreateController
  extends PhortuneMerchantController {

  protected function shouldRequireMerchantEditCapability() {
    return true;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    // TODO: Make this work again, or destroy it.
    return new Aphront404Response();

    $viewer = $request->getUser();

    $merchant = $this->loadMerchantAuthority();
    if (!$merchant) {
      return new Aphront404Response();
    }

    $this->setMerchant($merchant);
    $merchant_id = $merchant->getID();
    $cancel_uri = $this->getApplicationURI("/merchant/{$merchant_id}/");

    // Load the user to invoice, or prompt the viewer to select one.
    $target_user = null;
    $user_phid = head($request->getArr('userPHID'));
    if (!$user_phid) {
      $user_phid = $request->getStr('userPHID');
    }
    if ($user_phid) {
      $target_user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($user_phid))
        ->executeOne();
    }

    if (!$target_user) {
      $form = id(new AphrontFormView())
        ->setUser($viewer)
        ->appendRemarkupInstructions(pht('Choose a user to invoice.'))
        ->appendControl(
          id(new AphrontFormTokenizerControl())
            ->setLabel(pht('User'))
            ->setDatasource(new PhabricatorPeopleDatasource())
            ->setName('userPHID')
            ->setLimit(1));

      return $this->newDialog()
        ->setTitle(pht('Choose User'))
        ->appendForm($form)
        ->addCancelButton($cancel_uri)
        ->addSubmitButton(pht('Continue'));
    }

    // Load the account to invoice, or prompt the viewer to select one.
    $target_account = null;
    $account_phid = $request->getStr('accountPHID');
    if ($account_phid) {
      $target_account = id(new PhortuneAccountQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($account_phid))
        ->withMemberPHIDs(array($target_user->getPHID()))
        ->executeOne();
    }

    if (!$target_account) {
      $accounts = id(new PhortuneAccountQuery())
        ->setViewer($viewer)
        ->withMemberPHIDs(array($target_user->getPHID()))
        ->execute();

      $form = id(new AphrontFormView())
        ->setUser($viewer)
        ->addHiddenInput('userPHID', $target_user->getPHID())
        ->appendRemarkupInstructions(pht('Choose which account to invoice.'))
        ->appendControl(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('User'))
            ->setValue($viewer->renderHandle($target_user->getPHID())))
        ->appendControl(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Account'))
            ->setName('accountPHID')
            ->setValue($account_phid)
            ->setOptions(mpull($accounts, 'getName', 'getPHID')));

      return $this->newDialog()
        ->setTitle(pht('Choose Account'))
        ->appendForm($form)
        ->addCancelButton($cancel_uri)
        ->addSubmitButton(pht('Continue'));
    }


    // Now we build the actual invoice.
    $title = pht('New Invoice');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    $v_title = $request->getStr('title');
    $e_title = true;

    $v_name = $request->getStr('name');
    $e_name = true;

    $v_cost = $request->getStr('cost');
    $e_cost = true;

    $v_desc = $request->getStr('description');

    $v_quantity = 1;
    $e_quantity = null;

    $errors = array();
    if ($request->isFormPost() && $request->getStr('invoice')) {
      $v_quantity = $request->getStr('quantity');

      $e_title = null;
      $e_name = null;
      $e_cost = null;
      $e_quantity = null;

      if (!strlen($v_title)) {
        $e_title = pht('Required');
        $errors[] = pht('You must title this invoice.');
      }

      if (!strlen($v_name)) {
        $e_name = pht('Required');
        $errors[] = pht('You must provide a name for this purchase.');
      }

      if (!strlen($v_cost)) {
        $e_cost = pht('Required');
        $errors[] = pht('You must provide a cost for this purchase.');
      } else {
        try {
          $v_currency = PhortuneCurrency::newFromUserInput(
            $viewer,
            $v_cost);
        } catch (Exception $ex) {
          $errors[] = $ex->getMessage();
          $e_cost = pht('Invalid');
        }
      }

      if ((int)$v_quantity <= 0) {
        $e_quantity = pht('Invalid');
        $errors[] = pht('Quantity must be a positive integer.');
      }

      if (!$errors) {
        $unique = Filesystem::readRandomCharacters(16);

        $product = id(new PhortuneProductQuery())
          ->setViewer($target_user)
          ->withClassAndRef('PhortuneAdHocProduct', $unique)
          ->executeOne();

        $cart_implementation = new PhortuneAdHocCart();

        $cart = $target_account->newCart(
          $target_user,
          $cart_implementation,
          $merchant);

        $cart
          ->setMetadataValue('adhoc.title', $v_title)
          ->setMetadataValue('adhoc.description', $v_desc);

        $purchase = $cart->newPurchase($target_user, $product)
          ->setBasePriceAsCurrency($v_currency)
          ->setQuantity((int)$v_quantity)
          ->setMetadataValue('adhoc.name', $v_name)
          ->save();

        $cart
          ->setIsInvoice(1)
          ->save();

        $cart->activateCart();
        $cart_id = $cart->getID();

        $uri = "/merchant/{$merchant_id}/cart/{$cart_id}/";
        $uri = $this->getApplicationURI($uri);

        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('userPHID', $target_user->getPHID())
      ->addHiddenInput('accountPHID', $target_account->getPHID())
      ->addHiddenInput('invoice', true)
      ->appendControl(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('User'))
          ->setValue($viewer->renderHandle($target_user->getPHID())))
      ->appendControl(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Account'))
          ->setValue($viewer->renderHandle($target_account->getPHID())))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Invoice Title'))
          ->setName('title')
          ->setValue($v_title)
          ->setError($e_title))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Purchase Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Purchase Cost'))
          ->setName('cost')
          ->setValue($v_cost)
          ->setError($e_cost))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Quantity'))
          ->setName('quantity')
          ->setValue($v_quantity)
          ->setError($e_quantity))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Invoice Description'))
          ->setName('description')
          ->setValue($v_desc))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue(pht('Send Invoice')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setFormErrors($errors)
      ->setForm($form);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-plus-square');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    $navigation = $this->buildSideNavView('orders');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
      ->appendChild($view);
  }

}
