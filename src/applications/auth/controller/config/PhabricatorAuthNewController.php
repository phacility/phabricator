<?php

final class PhabricatorAuthNewController
  extends PhabricatorAuthProviderConfigController {

  public function handleRequest(AphrontRequest $request) {
    $this->requireApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);

    $viewer = $this->getViewer();
    $cancel_uri = $this->getApplicationURI();
    $locked_config_key = 'auth.lock-config';
    $is_locked = PhabricatorEnv::getEnvConfig($locked_config_key);

    if ($is_locked) {
      $message = pht(
        'Authentication provider configuration is locked, and can not be '.
        'changed without being unlocked. See the configuration setting %s '.
        'for details.',
        phutil_tag(
          'a',
          array(
            'href' => '/config/edit/'.$locked_config_key,
          ),
          $locked_config_key));

      return $this->newDialog()
        ->setUser($viewer)
        ->setTitle(pht('Authentication Config Locked'))
        ->appendChild($message)
        ->addCancelButton($cancel_uri);
    }

    $providers = PhabricatorAuthProvider::getAllBaseProviders();

    $configured = PhabricatorAuthProvider::getAllProviders();
    $configured_classes = array();
    foreach ($configured as $configured_provider) {
      $configured_classes[get_class($configured_provider)] = true;
    }

    // Sort providers by login order, and move disabled providers to the
    // bottom.
    $providers = msort($providers, 'getLoginOrder');
    $providers = array_diff_key($providers, $configured_classes) + $providers;

    $menu = id(new PHUIObjectItemListView())
      ->setViewer($viewer)
      ->setBig(true)
      ->setFlush(true);

    foreach ($providers as $provider_key => $provider) {
      $provider_class = get_class($provider);

      $provider_uri = id(new PhutilURI('/config/edit/'))
        ->replaceQueryParam('provider', $provider_class);
      $provider_uri = $this->getApplicationURI($provider_uri);

      $already_exists = isset($configured_classes[get_class($provider)]);

      $item = id(new PHUIObjectItemView())
        ->setHeader($provider->getNameForCreate())
        ->setImageIcon($provider->newIconView())
        ->addAttribute($provider->getDescriptionForCreate());

      if (!$already_exists) {
        $item
          ->setHref($provider_uri)
          ->setClickable(true);
      } else {
        $item->setDisabled(true);
      }

      if ($already_exists) {
        $messages = array();
        $messages[] = pht('You already have a provider of this type.');

        $info = id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
          ->setErrors($messages);

        $item->appendChild($info);
      }

      $menu->addItem($item);
    }

    return $this->newDialog()
      ->setTitle(pht('Add Auth Provider'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($menu)
      ->addCancelButton($cancel_uri);
  }

}
