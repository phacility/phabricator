<?php

final class PhortuneMerchantProviderEditController
  extends PhortuneMerchantController {

  protected function shouldRequireMerchantEditCapability() {
    return true;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $merchant = $this->getMerchant();

    $id = $request->getURIData('id');

    if ($id) {
      $provider_config = id(new PhortunePaymentProviderConfigQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->withMerchantPHIDs(array($merchant->getPHID()))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$provider_config) {
        return new Aphront404Response();
      }
      $is_new = false;
      $is_choose_type = false;

      $merchant = $provider_config->getMerchant();
      $merchant_id = $merchant->getID();
      $cancel_uri = $provider_config->getURI();
    } else {
      $merchant_id = $merchant->getID();

      $current_providers = id(new PhortunePaymentProviderConfigQuery())
        ->setViewer($viewer)
        ->withMerchantPHIDs(array($merchant->getPHID()))
        ->execute();
      $current_map = mgroup($current_providers, 'getProviderClass');

      $provider_config = PhortunePaymentProviderConfig::initializeNewProvider(
        $merchant);

      $is_new = true;

      $classes = PhortunePaymentProvider::getAllProviders();
      $class = $request->getStr('class');
      if (empty($classes[$class]) || isset($current_map[$class])) {
        return $this->processChooseClassRequest(
          $request,
          $merchant,
          $current_map);
      }

      $provider_config->setProviderClass($class);
      $cancel_uri = $merchant->getPaymentProvidersURI();
    }

    $provider = $provider_config->buildProvider();

    if ($is_new) {
      $title = pht('Create Payment Provider');
      $button_text = pht('Create Provider');
    } else {
      $title = pht(
        'Edit Payment Provider %d %s',
        $provider_config->getID(),
        $provider->getName());
      $button_text = pht('Save Changes');
    }

    $errors = array();
    if ($request->isFormPost() && $request->getStr('edit')) {
      $form_values = $provider->readEditFormValuesFromRequest($request);

      list($errors, $issues, $xaction_values) = $provider->processEditForm(
        $request,
        $form_values);

      if (!$errors) {
        // Find any secret fields which we're about to set to "*******"
        // (indicating that the user did not edit the value) and remove them
        // from the list of properties to update (so we don't write "******"
        // to permanent configuration.
        $secrets = $provider->getAllConfigurableSecretProperties();
        $secrets = array_fuse($secrets);
        foreach ($xaction_values as $key => $value) {
          if ($provider->isConfigurationSecret($value)) {
            unset($xaction_values[$key]);
          }
        }

        if ($provider->canRunConfigurationTest()) {
          $proxy = clone $provider;
          $proxy_config = clone $provider_config;
          $proxy_config->setMetadata(
            $xaction_values + $provider_config->getMetadata());
          $proxy->setProviderConfig($proxy_config);

          try {
            $proxy->runConfigurationTest();
          } catch (Exception $ex) {
            $errors[] = pht('Unable to connect to payment provider:');
            $errors[] = $ex->getMessage();
          }
        }

        if (!$errors) {
          $template = id(new PhortunePaymentProviderConfigTransaction())
            ->setTransactionType(
              PhortunePaymentProviderConfigTransaction::TYPE_PROPERTY);

          $xactions = array();

          if (!$provider_config->getID()) {
            $xactions[] = id(new PhortunePaymentProviderConfigTransaction())
              ->setTransactionType(
                PhortunePaymentProviderConfigTransaction::TYPE_CREATE)
              ->setNewValue(true);
          }

          foreach ($xaction_values as $key => $value) {
            $xactions[] = id(clone $template)
              ->setMetadataValue(
                PhortunePaymentProviderConfigTransaction::PROPERTY_KEY,
                $key)
              ->setNewValue($value);
          }

          $editor = id(new PhortunePaymentProviderConfigEditor())
            ->setActor($viewer)
            ->setContentSourceFromRequest($request)
            ->setContinueOnNoEffect(true);

          $editor->applyTransactions($provider_config, $xactions);

          $next_uri = $provider_config->getURI();

          return id(new AphrontRedirectResponse())->setURI($next_uri);
        }
      }
    } else {
      $form_values = $provider->readEditFormValuesFromProviderConfig();
      $issues = array();
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('class', $provider_config->getProviderClass())
      ->addHiddenInput('edit', true)
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Provider Type'))
          ->setValue($provider->getName()));

    $provider->extendEditForm($request, $form, $form_values, $issues);

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($button_text)
          ->addCancelButton($cancel_uri))
      ->appendChild(
        id(new AphrontFormDividerControl()))
      ->appendRemarkupInstructions(
        $provider->getConfigureInstructions());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($merchant->getName(), $cancel_uri);
    $crumbs->setBorder(true);

    if ($is_new) {
      $crumbs->addTextCrumb(pht('Add Provider'));
    } else {
      $crumbs->addTextCrumb(
        pht('Edit Provider %d', $provider_config->getID()));
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-pencil');

    $box = id(new PHUIObjectBoxView())
      ->setFormErrors($errors)
      ->setHeaderText(pht('Properties'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($form);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function processChooseClassRequest(
    AphrontRequest $request,
    PhortuneMerchant $merchant,
    array $current_map) {

    $viewer = $request->getUser();

    $providers = PhortunePaymentProvider::getAllProviders();
    $v_class = null;
    $errors = array();
    if ($request->isFormPost()) {
      $v_class = $request->getStr('class');
      if (!isset($providers[$v_class])) {
        $errors[] = pht('You must select a valid provider type.');
      }
    }

    $merchant_id = $merchant->getID();
    $cancel_uri = $this->getApplicationURI("merchant/{$merchant_id}/");

    if (!$v_class) {
      $v_class = key($providers);
    }

    $panel_classes = id(new AphrontFormRadioButtonControl())
      ->setName('class')
      ->setValue($v_class);

    $providers = msort($providers, 'getConfigureName');
    foreach ($providers as $class => $provider) {
      $disabled = isset($current_map[$class]);
      if ($disabled) {
        $description = phutil_tag(
          'em',
          array(),
          pht(
            'This merchant already has a payment account configured '.
            'with this provider.'));
      } else {
        $description = $provider->getConfigureDescription();
      }

      $panel_classes->addButton(
        $class,
        $provider->getConfigureName(),
        $description,
        null,
        $disabled);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht('Choose the type of payment provider to add:'))
      ->appendChild($panel_classes)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Continue'))
          ->addCancelButton($cancel_uri));

    $title = pht('Add Payment Provider');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($merchant->getName(), $cancel_uri);
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Provider'))
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-plus-square');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
