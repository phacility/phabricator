<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorUserSettingsController extends PhabricatorPeopleController {

  private $page;

  public function willProcessRequest(array $data) {
    $this->page = idx($data, 'page');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $pages = array(
//      'personal'    => 'Profile',
//      'password'    => 'Password',
//      'facebook'    => 'Facebook Account',
      'arcanist'    => 'Arcanist Certificate',
    );

    if (empty($pages[$this->page])) {
      $this->page = key($pages);
    }

    if ($request->isFormPost()) {
      switch ($this->page) {
        case 'arcanist':

          if (!$request->isDialogFormPost()) {
            $dialog = new AphrontDialogView();
            $dialog->setUser($user);
            $dialog->setTitle('Really regenerate session?');
            $dialog->setSubmitURI('/settings/page/arcanist/');
            $dialog->addSubmitButton('Regenerate');
            $dialog->addCancelbutton('/settings/page/arcanist/');
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
            ->setURI('/settings/page/arcanist/?regenerated=true');
      }
    }

    switch ($this->page) {
      case 'arcanist':
        $content = $this->renderArcanistCertificateForm();
        break;
      default:
        $content = 'derp derp';
        break;
    }


    $sidenav = new AphrontSideNavView();
    foreach ($pages as $page => $name) {
      $sidenav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/settings/page/'.$page.'/',
            'class' => ($page == $this->page)
              ? 'aphront-side-nav-selected'
              : null,
          ),
          phutil_escape_html($name)));
    }

    $sidenav->appendChild($content);

    return $this->buildStandardPageResponse(
      $sidenav,
      array(
        'title' => 'Account Settings',
      ));
  }

  private function renderArcanistCertificateForm() {
    $request = $this->getRequest();
    $user = $request->getUser();

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

    $host = PhabricatorEnv::getEnvConfig('phabricator.conduit-uri');

    $cert_form = new AphrontFormView();
    $cert_form
      ->setUser($user)
      ->appendChild(
        '<p class="aphront-form-instructions">Copy and paste this certificate '.
        'into your <tt>~/.arcrc</tt> in the "hosts" section to enable '.
        'Arcanist to authenticate against this host.</p>')
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
      ->setWorkflow(true)
      ->setAction('/settings/page/arcanist/')
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

    return $notice.$cert->render().$regen->render();
  }

}
