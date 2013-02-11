<?php

/**
 *
 * @phutil-external-symbol function recaptcha_get_html
 * @phutil-external-symbol function recaptcha_check_answer
 */
final class AphrontFormRecaptchaControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-recaptcha';
  }

  protected function shouldRender() {
    return self::isRecaptchaEnabled();
  }

  public static function isRecaptchaEnabled() {
    return PhabricatorEnv::getEnvConfig('recaptcha.enabled');
  }

  private static function requireLib() {
    $root = phutil_get_library_root('phabricator');
    require_once dirname($root).'/externals/recaptcha/recaptchalib.php';
  }

  public static function hasCaptchaResponse(AphrontRequest $request) {
    return $request->getBool('recaptcha_response_field');
  }

  public static function processCaptcha(AphrontRequest $request) {
    if (!self::isRecaptchaEnabled()) {
      return true;
    }

    self::requireLib();

    $challenge = $request->getStr('recaptcha_challenge_field');
    $response = $request->getStr('recaptcha_response_field');
    $resp = recaptcha_check_answer(
      PhabricatorEnv::getEnvConfig('recaptcha.private-key'),
      $_SERVER['REMOTE_ADDR'],
      $challenge,
      $response);

    return (bool)@$resp->is_valid;
  }

  protected function renderInput() {
    self::requireLib();

    $uri = new PhutilURI(PhabricatorEnv::getEnvConfig('phabricator.base-uri'));
    $protocol = $uri->getProtocol();
    $use_ssl = ($protocol == 'https');

    return phutil_safe_html(recaptcha_get_html(
      PhabricatorEnv::getEnvConfig('recaptcha.public-key'),
      $error = null,
      $use_ssl));
  }

}
