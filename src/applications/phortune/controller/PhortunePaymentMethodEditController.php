<?php

final class PhortunePaymentMethodEditController
  extends PhortuneController {

  private $accountID;

  public function willProcessRequest(array $data) {
    $this->accountID = $data['accountID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $account = id(new PhortuneAccountQuery())
      ->setViewer($user)
      ->withIDs(array($this->accountID))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $cancel_uri = $this->getApplicationURI($account->getID().'/');
    $account_uri = $this->getApplicationURI($account->getID().'/');

    $providers = PhortunePaymentProvider::getProvidersForAddPaymentMethod();
    if (!$providers) {
      throw new Exception(
        "There are no payment providers enabled that can add payment ".
        "methods.");
    }

    $provider_key = $request->getStr('providerKey');
    if (empty($providers[$provider_key])) {
      $choices = array();
      foreach ($providers as $provider) {
        $choices[] = $this->renderSelectProvider($provider);
      }
      return $this->buildResponse($choices, $account_uri);
    }

    $provider = $providers[$provider_key];

    $errors = array();
    if ($request->isFormPost() && $request->getBool('isProviderForm')) {
      $method = id(new PhortunePaymentMethod())
        ->setAccountPHID($account->getPHID())
        ->setAuthorPHID($user->getPHID())
        ->setStatus(PhortunePaymentMethod::STATUS_ACTIVE)
        ->setMetadataValue('providerKey', $provider->getProviderKey());

      $errors = $provider->createPaymentMethodFromRequest($request, $method);

      if (!$errors) {
        $method->save();

        $save_uri = new PhutilURI($account_uri);
        $save_uri->setFragment('payment');
        return id(new AphrontRedirectResponse())->setURI($save_uri);
      } else {
        $dialog = id(new AphrontDialogView())
          ->setUser($user)
          ->setTitle(pht('Error Adding Payment Method'))
          ->appendChild(id(new AphrontErrorView())->setErrors($errors))
          ->addCancelButton($request->getRequestURI());

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }
    }

    $form = $provider->renderCreatePaymentMethodForm($request, $errors);

    $form
      ->setUser($user)
      ->setAction($request->getRequestURI())
      ->setWorkflow(true)
      ->addHiddenInput('providerKey', $provider_key)
      ->addHiddenInput('isProviderForm', true)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Add Payment Method'))
          ->addCancelButton($account_uri));

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setErrors($errors);
    }

    return $this->buildResponse(
      array($errors, $form),
      $account_uri);
  }

  private function renderSelectProvider(
    PhortunePaymentProvider $provider) {

    $request = $this->getRequest();
    $user = $request->getUser();

    $description = $provider->getPaymentMethodDescription();
    $icon = $provider->getPaymentMethodIcon();
    $details = $provider->getPaymentMethodProviderDescription();

    $button = phutil_tag(
      'button',
      array(
        'class' => 'grey',
      ),
      array(
        $description,
        phutil_tag('br'),
        $icon,
        $details,
      ));

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->addHiddenInput('providerKey', $provider->getProviderKey())
      ->appendChild($button);

    return $form;
  }

  private function buildResponse($content, $account_uri) {
    $request = $this->getRequest();

    $title = pht('Add Payment Method');
    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Account'))
        ->setHref($account_uri));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Payment Methods'))
        ->setHref($request->getRequestURI()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $content,
      ),
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

}
