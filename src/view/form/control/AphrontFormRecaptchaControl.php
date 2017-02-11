<?php

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

  public static function hasCaptchaResponse(AphrontRequest $request) {
    return $request->getBool('g-recaptcha-response');
  }

  public static function processCaptcha(AphrontRequest $request) {
    if (!self::isRecaptchaEnabled()) {
      return true;
    }

    $uri = 'https://www.google.com/recaptcha/api/siteverify';
    $params = array(
      'secret'   => PhabricatorEnv::getEnvConfig('recaptcha.private-key'),
      'response' => $request->getStr('g-recaptcha-response'),
      'remoteip' => $request->getRemoteAddress(),
    );

    list($body) = id(new HTTPSFuture($uri, $params))
      ->setMethod('POST')
      ->resolvex();

    $json = phutil_json_decode($body);
    return (bool)idx($json, 'success');
  }

  protected function renderInput() {
    $js = 'https://www.google.com/recaptcha/api.js';
    $pubkey = PhabricatorEnv::getEnvConfig('recaptcha.public-key');

    return array(
      phutil_tag('div', array(
        'class'        => 'g-recaptcha',
        'data-sitekey' => $pubkey,
      )),

      phutil_tag('script', array(
        'type' => 'text/javascript',
        'src'  => $js,
      )),
    );
  }
}
