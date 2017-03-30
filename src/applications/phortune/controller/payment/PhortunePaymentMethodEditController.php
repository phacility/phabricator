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

    $account = $method->getAccount();
    $account_uri = $this->getApplicationURI($account->getID().'/');

    if ($request->isFormPost()) {

      $name = $request->getStr('name');

      // TODO: Use ApplicationTransactions

      $method->setName($name);
      $method->save();

      return id(new AphrontRedirectResponse())->setURI($account_uri);
    }

    $provider = $method->buildPaymentProvider();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
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
          ->addCancelButton($account_uri)
          ->setValue(pht('Save Changes')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Payment Method'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($account->getName(), $account_uri);
    $crumbs->addTextCrumb($method->getDisplayName());
    $crumbs->addTextCrumb(pht('Edit'));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Edit Payment Method'))
      ->setHeaderIcon('fa-pencil');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    return $this->newPage()
      ->setTitle(pht('Edit Payment Method'))
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
