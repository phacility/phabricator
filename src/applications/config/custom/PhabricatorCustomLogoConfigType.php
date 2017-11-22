<?php

final class PhabricatorCustomLogoConfigType
  extends PhabricatorConfigOptionType {

  public static function getLogoImagePHID() {
    $logo = PhabricatorEnv::getEnvConfig('ui.logo');
    return idx($logo, 'logoImagePHID');
  }

  public static function getLogoWordmark() {
    $logo = PhabricatorEnv::getEnvConfig('ui.logo');
    return idx($logo, 'wordmarkText');
  }

  public static function getLogoURI(PhabricatorUser $viewer) {
    $logo_uri = null;

    $custom_header = self::getLogoImagePHID();
    if ($custom_header) {
      $cache = PhabricatorCaches::getImmutableCache();
      $cache_key_logo = 'ui.custom-header.logo-phid.v3.'.$custom_header;
      $logo_uri = $cache->getKey($cache_key_logo);

      if (!$logo_uri) {
        // NOTE: If the file policy has been changed to be restrictive, we'll
        // miss here and just show the default logo. The cache will fill later
        // when someone who can see the file loads the page. This might be a
        // little spooky, see T11982.
        $files = id(new PhabricatorFileQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($custom_header))
          ->execute();
        $file = head($files);
        if ($file) {
          $logo_uri = $file->getViewURI();
          $cache->setKey($cache_key_logo, $logo_uri);
        }
      }
    }

    if (!$logo_uri) {
      $logo_uri = celerity_get_resource_uri('/rsrc/image/logo/light-eye.png');
    }

    return $logo_uri;
  }

  public function validateOption(PhabricatorConfigOption $option, $value) {
    if (!is_array($value)) {
      throw new Exception(
        pht(
          'Logo configuration is not valid: value must be a dictionary.'));
    }

    PhutilTypeSpec::checkMap(
      $value,
      array(
        'logoImagePHID' => 'optional string|null',
        'wordmarkText' => 'optional string|null',
      ));
  }

  public function readRequest(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {

    $viewer = $request->getViewer();
    $view_policy = PhabricatorPolicies::POLICY_PUBLIC;

    if ($request->getBool('removeLogo')) {
      $logo_image_phid = null;
    } else if ($request->getFileExists('logoImage')) {
      $logo_image = PhabricatorFile::newFromPHPUpload(
        idx($_FILES, 'logoImage'),
        array(
          'name' => 'logo',
          'authorPHID' => $viewer->getPHID(),
          'viewPolicy' => $view_policy,
          'canCDN' => true,
          'isExplicitUpload' => true,
        ));
      $logo_image_phid = $logo_image->getPHID();
    } else {
      $logo_image_phid = self::getLogoImagePHID();
    }

    $wordmark_text = $request->getStr('wordmarkText');

    $value = array(
      'logoImagePHID' => $logo_image_phid,
      'wordmarkText' => $wordmark_text,
    );

    $errors = array();
    $e_value = null;

    try {
      $this->validateOption($option, $value);
    } catch (Exception $ex) {
      $e_value = pht('Invalid');
      $errors[] = $ex->getMessage();
      $value = array();
    }

    return array($e_value, $errors, $value, phutil_json_encode($value));
  }

  public function renderControls(
    PhabricatorConfigOption $option,
    $display_value,
    $e_value) {

    try {
      $value = phutil_json_decode($display_value);
    } catch (Exception $ex) {
      $value = array();
    }

    $logo_image_phid = idx($value, 'logoImagePHID');
    $wordmark_text = idx($value, 'wordmarkText');

    $controls = array();

    // TODO: This should be a PHUIFormFileControl, but that currently only
    // works in "workflow" forms. It isn't trivial to convert this form into
    // a workflow form, nor is it trivial to make the newer control work
    // in non-workflow forms.
    $controls[] = id(new AphrontFormFileControl())
      ->setName('logoImage')
      ->setLabel(pht('Logo Image'));

    if ($logo_image_phid) {
      $controls[] = id(new AphrontFormCheckboxControl())
        ->addCheckbox(
          'removeLogo',
          1,
          pht('Remove Custom Logo'));
    }

    $controls[] = id(new AphrontFormTextControl())
      ->setName('wordmarkText')
      ->setLabel(pht('Wordmark'))
      ->setPlaceholder(pht('Phabricator'))
      ->setValue($wordmark_text);

    return $controls;
  }


}
