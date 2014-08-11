<?php

final class PhortunePaymentMethodEditController
  extends PhortuneController {

  private $methodID;

  public function willProcessRequest(array $data) {
    $this->methodID = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $method = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->methodID))
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
      ->setHeaderText(pht('Edit Payment Method'))
      ->appendChild($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($account->getName(), $account_uri);
    $crumbs->addTextCrumb($method->getDisplayName());
    $crumbs->addTextCrumb(pht('Edit'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => pht('Edit Payment Method'),
      ));
  }

}
