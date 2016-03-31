<?php

final class PhabricatorAuthEditController
  extends PhabricatorAuthProviderConfigController {

  public function handleRequest(AphrontRequest $request) {
    $this->requireApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);
    $viewer = $request->getUser();
    $provider_class = $request->getURIData('className');
    $config_id = $request->getURIData('id');

    if ($config_id) {
      $config = id(new PhabricatorAuthProviderConfigQuery())
        ->setViewer($viewer)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withIDs(array($config_id))
        ->executeOne();
      if (!$config) {
        return new Aphront404Response();
      }

      $provider = $config->getProvider();
      if (!$provider) {
        return new Aphront404Response();
      }

      $is_new = false;
    } else {
      $provider = null;

      $providers = PhabricatorAuthProvider::getAllBaseProviders();
      foreach ($providers as $candidate_provider) {
        if (get_class($candidate_provider) === $provider_class) {
          $provider = $candidate_provider;
          break;
        }
      }

      if (!$provider) {
        return new Aphront404Response();
      }

      // TODO: When we have multi-auth providers, support them here.

      $configs = id(new PhabricatorAuthProviderConfigQuery())
        ->setViewer($viewer)
        ->withProviderClasses(array(get_class($provider)))
        ->execute();

      if ($configs) {
        $id = head($configs)->getID();
        $dialog = id(new AphrontDialogView())
          ->setUser($viewer)
          ->setMethod('GET')
          ->setSubmitURI($this->getApplicationURI('config/edit/'.$id.'/'))
          ->setTitle(pht('Provider Already Configured'))
          ->appendChild(
            pht(
              'This provider ("%s") already exists, and you can not add more '.
              'than one instance of it. You can edit the existing provider, '.
              'or you can choose a different provider.',
              $provider->getProviderName()))
          ->addCancelButton($this->getApplicationURI('config/new/'))
          ->addSubmitButton(pht('Edit Existing Provider'));

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }

      $config = $provider->getDefaultProviderConfig();
      $provider->attachProviderConfig($config);

      $is_new = true;
    }

    $errors = array();

    $v_login = $config->getShouldAllowLogin();
    $v_registration = $config->getShouldAllowRegistration();
    $v_link = $config->getShouldAllowLink();
    $v_unlink = $config->getShouldAllowUnlink();
    $v_trust_email = $config->getShouldTrustEmails();
    $v_auto_login = $config->getShouldAutoLogin();

    if ($request->isFormPost()) {

      $properties = $provider->readFormValuesFromRequest($request);
      list($errors, $issues, $properties) = $provider->processEditForm(
        $request,
        $properties);

      $xactions = array();

      if (!$errors) {
        if ($is_new) {
          if (!strlen($config->getProviderType())) {
            $config->setProviderType($provider->getProviderType());
          }
          if (!strlen($config->getProviderDomain())) {
            $config->setProviderDomain($provider->getProviderDomain());
          }
        }

        $xactions[] = id(new PhabricatorAuthProviderConfigTransaction())
          ->setTransactionType(
            PhabricatorAuthProviderConfigTransaction::TYPE_LOGIN)
          ->setNewValue($request->getInt('allowLogin', 0));

        $xactions[] = id(new PhabricatorAuthProviderConfigTransaction())
          ->setTransactionType(
            PhabricatorAuthProviderConfigTransaction::TYPE_REGISTRATION)
          ->setNewValue($request->getInt('allowRegistration', 0));

        $xactions[] = id(new PhabricatorAuthProviderConfigTransaction())
          ->setTransactionType(
            PhabricatorAuthProviderConfigTransaction::TYPE_LINK)
          ->setNewValue($request->getInt('allowLink', 0));

        $xactions[] = id(new PhabricatorAuthProviderConfigTransaction())
          ->setTransactionType(
            PhabricatorAuthProviderConfigTransaction::TYPE_UNLINK)
          ->setNewValue($request->getInt('allowUnlink', 0));

        $xactions[] = id(new PhabricatorAuthProviderConfigTransaction())
          ->setTransactionType(
            PhabricatorAuthProviderConfigTransaction::TYPE_TRUST_EMAILS)
          ->setNewValue($request->getInt('trustEmails', 0));

        if ($provider instanceof PhabricatorPhabricatorAuthProvider) {
          $xactions[] = id(new PhabricatorAuthProviderConfigTransaction())
            ->setTransactionType(
              PhabricatorAuthProviderConfigTransaction::TYPE_AUTO_LOGIN)
            ->setNewValue($request->getInt('autoLogin', 0));
        }

        foreach ($properties as $key => $value) {
          $xactions[] = id(new PhabricatorAuthProviderConfigTransaction())
            ->setTransactionType(
              PhabricatorAuthProviderConfigTransaction::TYPE_PROPERTY)
            ->setMetadataValue('auth:property', $key)
            ->setNewValue($value);
        }

        if ($is_new) {
          $config->save();
        }

        $editor = id(new PhabricatorAuthProviderConfigEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->applyTransactions($config, $xactions);

        if ($provider->hasSetupStep() && $is_new) {
          $id = $config->getID();
          $next_uri = $this->getApplicationURI('config/edit/'.$id.'/');
        } else {
          $next_uri = $this->getApplicationURI();
        }

        return id(new AphrontRedirectResponse())->setURI($next_uri);
      }
    } else {
      $properties = $provider->readFormValuesFromProvider();
      $issues = array();
    }

    if ($is_new) {
      if ($provider->hasSetupStep()) {
        $button = pht('Next Step');
      } else {
        $button = pht('Add Provider');
      }
      $crumb = pht('Add Provider');
      $title = pht('Add Auth Provider');
      $header_icon = 'fa-plus-square';
      $cancel_uri = $this->getApplicationURI('/config/new/');
    } else {
      $button = pht('Save');
      $crumb = pht('Edit Provider');
      $title = pht('Edit Auth Provider');
      $header_icon = 'fa-pencil';
      $cancel_uri = $this->getApplicationURI();
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('%s: %s', $title, $provider->getProviderName()))
      ->setHeaderIcon($header_icon);

    if ($config->getIsEnabled()) {
      $status_name = pht('Enabled');
      $status_color = 'green';
      $status_icon = 'fa-check';
      $header->setStatus($status_icon, $status_color, $status_name);
    } else if (!$is_new) {
      $status_name = pht('Disabled');
      $status_color = 'indigo';
      $status_icon = 'fa-ban';
      $header->setStatus($status_icon, $status_color, $status_name);
    }

    $config_name = 'auth.email-domains';
    $config_href = '/config/edit/'.$config_name.'/';

    $email_domains = PhabricatorEnv::getEnvConfig($config_name);
    if ($email_domains) {
      $registration_warning = pht(
        'Users will only be able to register with a verified email address '.
        'at one of the configured [[ %s | %s ]] domains: **%s**',
        $config_href,
        $config_name,
        implode(', ', $email_domains));
    } else {
      $registration_warning = pht(
        "NOTE: Any user who can browse to this install's login page will be ".
        "able to register a Phabricator account. To restrict who can register ".
        "an account, configure [[ %s | %s ]].",
        $config_href,
        $config_name);
    }

    $str_login = array(
      phutil_tag('strong', array(), pht('Allow Login:')),
      ' ',
      pht(
        'Allow users to log in using this provider. If you disable login, '.
        'users can still use account integrations for this provider.'),
    );

    $str_registration = array(
      phutil_tag('strong', array(), pht('Allow Registration:')),
      ' ',
      pht(
        'Allow users to register new Phabricator accounts using this '.
        'provider. If you disable registration, users can still use this '.
        'provider to log in to existing accounts, but will not be able to '.
        'create new accounts.'),
    );

    $str_link = hsprintf(
      '<strong>%s:</strong> %s',
      pht('Allow Linking Accounts'),
      pht(
        'Allow users to link account credentials for this provider to '.
        'existing Phabricator accounts. There is normally no reason to '.
        'disable this unless you are trying to move away from a provider '.
        'and want to stop users from creating new account links.'));

    $str_unlink = hsprintf(
      '<strong>%s:</strong> %s',
      pht('Allow Unlinking Accounts'),
      pht(
        'Allow users to unlink account credentials for this provider from '.
        'existing Phabricator accounts. If you disable this, Phabricator '.
        'accounts will be permanently bound to provider accounts.'));

    $str_trusted_email = hsprintf(
      '<strong>%s:</strong> %s',
      pht('Trust Email Addresses'),
      pht(
        'Phabricator will skip email verification for accounts registered '.
        'through this provider.'));
    $str_auto_login = hsprintf(
      '<strong>%s:</strong> %s',
      pht('Allow Auto Login'),
      pht(
        'Phabricator will automatically login with this provider if it is '.
        'the only available provider.'));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel(pht('Allow'))
          ->addCheckbox(
            'allowLogin',
            1,
            $str_login,
            $v_login))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'allowRegistration',
            1,
            $str_registration,
            $v_registration))
      ->appendRemarkupInstructions($registration_warning)
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'allowLink',
            1,
            $str_link,
            $v_link))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'allowUnlink',
            1,
            $str_unlink,
            $v_unlink));

    if ($provider->shouldAllowEmailTrustConfiguration()) {
      $form->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'trustEmails',
            1,
            $str_trusted_email,
            $v_trust_email));
    }

    if ($provider instanceof PhabricatorPhabricatorAuthProvider) {
      $form->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'autoLogin',
            1,
            $str_auto_login,
            $v_auto_login));
    }

    $provider->extendEditForm($request, $form, $properties, $issues);

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($button));

    $help = $provider->getConfigurationHelp();
    if ($help) {
      $form->appendChild(id(new PHUIFormDividerControl()));
      $form->appendRemarkupInstructions($help);
    }

    $footer = $provider->renderConfigurationFooter();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($crumb);
    $crumbs->setBorder(true);

    $timeline = null;
    if (!$is_new) {
      $timeline = $this->buildTransactionTimeline(
        $config,
        new PhabricatorAuthProviderConfigTransactionQuery());
      $xactions = $timeline->getTransactions();
      foreach ($xactions as $xaction) {
        $xaction->setProvider($provider);
      }
      $timeline->setShouldTerminate(true);
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Provider'))
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $form_box,
        $footer,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
