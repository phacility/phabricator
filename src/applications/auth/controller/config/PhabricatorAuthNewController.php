<?php

final class PhabricatorAuthNewController
  extends PhabricatorAuthProviderConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $providers = PhabricatorAuthProvider::getAllBaseProviders();

    $e_provider = null;
    $errors = array();

    if ($request->isFormPost()) {
      $provider_string = $request->getStr('provider');
      if (!strlen($provider_string)) {
        $e_provider = pht('Required');
        $errors[] = pht('You must select an authentication provider.');
      } else {
        $found = false;
        foreach ($providers as $provider) {
          if (get_class($provider) === $provider_string) {
            $found = true;
            break;
          }
        }
        if (!$found) {
          $e_provider = pht('Invalid');
          $errors[] = pht('You must select a valid provider.');
        }
      }

      if (!$errors) {
        return id(new AphrontRedirectResponse())->setURI(
          $this->getApplicationURI('/config/new/'.$provider_string.'/'));
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())->setErrors($errors);
    }

    $options = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Provider'))
      ->setName('provider')
      ->setError($e_provider);

    $providers = msort($providers, 'getProviderName');
    foreach ($providers as $provider) {
      $options->addButton(
        get_class($provider),
        $provider->getNameForCreate(),
        $provider->getDescriptionForCreate());
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($options)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($this->getApplicationURI())
          ->setValue(pht('Continue')));


    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Add Provider')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $errors,
        $form,
      ),
      array(
        'title' => pht('Add Authentication Provider'),
        'dust' => true,
        'device' => true,
      ));
  }

}
