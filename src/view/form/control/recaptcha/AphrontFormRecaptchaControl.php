<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 *
 * @phutil-external-symbol function recaptcha_get_html
 * @phutil-external-symbol function recaptcha_check_answer
 */
class AphrontFormRecaptchaControl extends AphrontFormControl {

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

    return recaptcha_get_html(
      PhabricatorEnv::getEnvConfig('recaptcha.public-key'),
      $error = null,
      $use_ssl = false);
  }

}
