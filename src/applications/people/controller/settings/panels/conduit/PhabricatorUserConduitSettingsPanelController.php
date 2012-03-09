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

final class PhabricatorUserConduitSettingsPanelController
  extends PhabricatorUserSettingsPanelController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      if (!$request->isDialogFormPost()) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($user);
        $dialog->setTitle('Really regenerate session?');
        $dialog->setSubmitURI('/settings/page/conduit/');
        $dialog->addSubmitButton('Regenerate');
        $dialog->addCancelbutton('/settings/page/conduit/');
        $dialog->appendChild(
          '<p>Really destroy the old certificate? Any established '.
          'sessions will be terminated.');

        return id(new AphrontDialogResponse())
          ->setDialog($dialog);
      }

      $conn = $user->establishConnection('w');
      queryfx(
        $conn,
        'DELETE FROM %T WHERE userPHID = %s AND type LIKE %>',
        PhabricatorUser::SESSION_TABLE,
        $user->getPHID(),
        'conduit');

      // This implicitly regenerates the certificate.
      $user->setConduitCertificate(null);
      $user->save();
      return id(new AphrontRedirectResponse())
        ->setURI('/settings/page/conduit/?regenerated=true');
    }

    if ($request->getStr('regenerated')) {
      $notice = new AphrontErrorView();
      $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $notice->setTitle('Certificate Regenerated');
      $notice->appendChild(
        '<p>Your old certificate has been destroyed and you have been issued '.
        'a new certificate. Sessions established under the old certificate '.
        'are no longer valid.</p>');
      $notice = $notice->render();
    } else {
      $notice = null;
    }

    $cert_form = new AphrontFormView();
    $cert_form
      ->setUser($user)
      ->appendChild(
        '<p class="aphront-form-instructions">This certificate allows you to '.
        'authenticate over Conduit, the Phabricator API. Normally, you just '.
        'run <tt>arc install-certificate</tt> to install it.')
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Certificate')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_SHORT)
          ->setValue($user->getConduitCertificate()));

    $cert = new AphrontPanelView();
    $cert->setHeader('Arcanist Certificate');
    $cert->appendChild($cert_form);
    $cert->setWidth(AphrontPanelView::WIDTH_FORM);

    $regen_form = new AphrontFormView();
    $regen_form
      ->setUser($user)
      ->setAction('/settings/page/conduit/')
      ->appendChild(
        '<p class="aphront-form-instructions">You can regenerate this '.
        'certificate, which will invalidate the old certificate and create '.
        'a new one.</p>')
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Regenerate Certificate'));

    $regen = new AphrontPanelView();
    $regen->setHeader('Regenerate Certificate');
    $regen->appendChild($regen_form);
    $regen->setWidth(AphrontPanelView::WIDTH_FORM);

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $notice,
          $cert,
          $regen,
        ));
  }
}
