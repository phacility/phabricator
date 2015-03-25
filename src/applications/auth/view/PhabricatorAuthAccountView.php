<?php

final class PhabricatorAuthAccountView extends AphrontView {

  private $externalAccount;
  private $provider;

  public function setExternalAccount(
    PhabricatorExternalAccount $external_account) {
    $this->externalAccount = $external_account;
    return $this;
  }

  public function setAuthProvider(PhabricatorAuthProvider $provider) {
    $this->provider = $provider;
    return $this;
  }

  public function render() {
    $account = $this->externalAccount;
    $provider = $this->provider;

    require_celerity_resource('auth-css');

    $content = array();

    $dispname = $account->getDisplayName();
    $username = $account->getUsername();
    $realname = $account->getRealName();

    $use_name = null;
    if (strlen($dispname)) {
      $use_name = $dispname;
    } else if (strlen($username) && strlen($realname)) {
      $use_name = $username.' ('.$realname.')';
    } else if (strlen($username)) {
      $use_name = $username;
    } else if (strlen($realname)) {
      $use_name = $realname;
    } else {
      $use_name = $account->getAccountID();
    }

    $content[] = phutil_tag(
      'div',
      array(
        'class' => 'auth-account-view-name',
      ),
      $use_name);

    if ($provider) {
      $prov_name = pht('%s Account', $provider->getProviderName());
    } else {
      $prov_name = pht('"%s" Account', $account->getProviderType());
    }

    $content[] = phutil_tag(
      'div',
      array(
        'class' => 'auth-account-view-provider-name',
      ),
      array(
        $prov_name,
        " \xC2\xB7 ",
        $account->getAccountID(),
      ));

    $account_uri = $account->getAccountURI();
    if (strlen($account_uri)) {

      // Make sure we don't link a "javascript:" URI if a user somehow
      // managed to get one here.

      if (PhabricatorEnv::isValidRemoteURIForLink($account_uri)) {
        $account_uri = phutil_tag(
          'a',
          array(
            'href' => $account_uri,
            'target' => '_blank',
          ),
          $account_uri);
      }

      $content[] = phutil_tag(
        'div',
        array(
          'class' => 'auth-account-view-account-uri',
        ),
        $account_uri);
    }

    $image_uri = $account->getProfileImageFile()->getProfileThumbURI();

    return phutil_tag(
      'div',
      array(
        'class' => 'auth-account-view',
        'style' => 'background-image: url('.$image_uri.')',
      ),
      $content);
  }

}
