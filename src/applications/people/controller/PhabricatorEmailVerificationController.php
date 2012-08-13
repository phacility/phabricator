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

final class PhabricatorEmailVerificationController
  extends PhabricatorPeopleController {

  private $code;

  public function willProcessRequest(array $data) {
    $this->code = $data['code'];
  }

  public function shouldRequireEmailVerification() {
    // Since users need to be able to hit this endpoint in order to verify
    // email, we can't ever require email verification here.
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $email = id(new PhabricatorUserEmail())->loadOneWhere(
      'userPHID = %s AND verificationCode = %s',
      $user->getPHID(),
      $this->code);

    $home_link = phutil_render_tag(
      'a',
      array(
        'href' => '/',
      ),
      'Continue to Phabricator');
    $home_link = '<br /><p><strong>'.$home_link.'</strong></p>';

    $settings_link = phutil_render_tag(
      'a',
      array(
        'href' => '/settings/panel/email/',
      ),
      'Return to Email Settings');
    $settings_link = '<br /><p><strong>'.$settings_link.'</strong></p>';


    if (!$email) {
      $content = id(new AphrontErrorView())
        ->setTitle('Unable To Verify')
        ->appendChild(
          '<p>The verification code is incorrect, the email address has '.
          'been removed, or the email address is owned by another user. Make '.
          'sure you followed the link in the email correctly.</p>');
    } else if ($email->getIsVerified()) {
      $content = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle('Address Already Verified')
        ->appendChild(
          '<p>This email address has already been verified.</p>'.
          $settings_link);
    } else {

      $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
        $email->setIsVerified(1);
        $email->save();
      unset($guard);

      $content = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle('Address Verified')
        ->appendChild(
          '<p>This email address has now been verified. Thanks!</p>'.
          $home_link.
          $settings_link);
    }

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => 'Verify Email',
      ));
  }

}
