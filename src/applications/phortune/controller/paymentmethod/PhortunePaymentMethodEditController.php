<?php

final class PhortunePaymentMethodEditController
  extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $method_id = $request->getURIData('id');

    $method = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withIDs(array($method_id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$method) {
      return new Aphront404Response();
    }

    $next_uri = $method->getURI();

    $account = $method->getAccount();
    $v_name = $method->getName();

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');

      $xactions = array();

      $xactions[] = $method->getApplicationTransactionTemplate()
        ->setTransactionType(
          PhortunePaymentMethodNameTransaction::TRANSACTIONTYPE)
        ->setNewValue($v_name);

      $editor = id(new PhortunePaymentMethodEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $editor->applyTransactions($method, $xactions);

      return id(new AphrontRedirectResponse())->setURI($next_uri);
    }

    $provider = $method->buildPaymentProvider();

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($method->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Details'))
          ->setValue($method->getSummary()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Expires'))
          ->setValue($method->getDisplayExpires()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($next_uri)
          ->setValue(pht('Save Changes')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Payment Method'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($account->getName(), $account->getURI())
      ->addTextCrumb(pht('Payment Methods'), $account->getPaymentMethodsURI())
      ->addTextCrumb($method->getObjectName(), $method->getURI())
      ->addTextCrumb(pht('Edit'))
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Edit Payment Method'))
      ->setHeaderIcon('fa-pencil');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $box,
        ));

    return $this->newPage()
      ->setTitle(pht('Edit Payment Method'))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
