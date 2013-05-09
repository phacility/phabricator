<?php

final class PhortuneProductEditController extends PhabricatorController {

  private $productID;

  public function willProcessRequest(array $data) {
    $this->productID = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->productID) {
      $product = id(new PhortuneProductQuery())
        ->setViewer($user)
        ->withIDs(array($this->productID))
        ->executeOne();
      if (!$product) {
        return new Aphront404Response();
      }

      $is_create = false;
      $cancel_uri = $this->getApplicationURI(
        'product/view/'.$this->productID.'/');
    } else {
      $product = new PhortuneProduct();
      $is_create = true;
      $cancel_uri = $this->getApplicationURI('product/');
    }

    $v_name = $product->getProductName();
    $v_type = $product->getProductType();
    $v_price = (int)$product->getPriceInCents();
    $display_price = PhortuneCurrency::newFromUSDCents($v_price)
      ->formatForDisplay();

    $e_name = true;
    $e_type = null;
    $e_price = true;
    $errors = array();

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      if (!strlen($v_name)) {
        $e_name = pht('Required');
        $errors[] = pht('Product must have a name.');
      } else {
        $e_name = null;
      }

      if ($is_create) {
        $v_type = $request->getStr('type');
        $type_map = PhortuneProduct::getTypeMap();
        if (empty($type_map[$v_type])) {
          $e_type = pht('Invalid');
          $errors[] = pht('Product type is invalid.');
        } else {
          $e_type = null;
        }
      }

      $display_price = $request->getStr('price');
      try {
        $v_price = PhortuneCurrency::newFromUserInput($user, $display_price)
          ->getValue();
        $e_price = null;
      } catch (Exception $ex) {
        $errors[] = pht('Price should be formatted as: $1.23');
        $e_price = pht('Invalid');
      }

      if (!$errors) {
        $xactions = array();

        $xactions[] = id(new PhortuneProductTransaction())
          ->setTransactionType(PhortuneProductTransaction::TYPE_NAME)
          ->setNewValue($v_name);

        $xactions[] = id(new PhortuneProductTransaction())
          ->setTransactionType(PhortuneProductTransaction::TYPE_TYPE)
          ->setNewValue($v_type);

        $xactions[] = id(new PhortuneProductTransaction())
          ->setTransactionType(PhortuneProductTransaction::TYPE_PRICE)
          ->setNewValue($v_price);

        $editor = id(new PhortuneProductEditor())
          ->setActor($user)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request);

        $editor->applyTransactions($product, $xactions);

        return id(new AphrontRedirectResponse())->setURI(
          $this->getApplicationURI('product/view/'.$product->getID().'/'));
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setErrors($errors);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Type'))
          ->setName('type')
          ->setValue($v_type)
          ->setError($e_type)
          ->setOptions(PhortuneProduct::getTypeMap())
          ->setDisabled(!$is_create))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Price'))
          ->setName('price')
          ->setValue($display_price)
          ->setError($e_price))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(
            $is_create
              ? pht('Create Product')
              : pht('Save Product'))
          ->addCancelButton($cancel_uri));

    $title = pht('Edit Product');
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Products'))
        ->setHref($this->getApplicationURI('product/')));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($is_create ? pht('Create') : pht('Edit'))
        ->setHref($request->getRequestURI()));

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Edit Product'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $errors,
        $form,
      ),
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

}
