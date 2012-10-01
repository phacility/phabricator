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

abstract class PhabricatorController extends AphrontController {

  private $handles;

  public function shouldRequireLogin() {

    // If this install is configured to allow public resources and the
    // controller works in public mode, allow the request through.
    $is_public_allowed = PhabricatorEnv::getEnvConfig('policy.allow-public');
    if ($is_public_allowed && $this->shouldAllowPublic()) {
      return false;
    }

    return true;
  }

  public function shouldRequireAdmin() {
    return false;
  }

  public function shouldRequireEnabledUser() {
    return true;
  }

  public function shouldAllowPublic() {
    return false;
  }

  public function shouldRequireEmailVerification() {
    $need_verify = PhabricatorUserEmail::isEmailVerificationRequired();
    $need_login = $this->shouldRequireLogin();

    return ($need_login && $need_verify);
  }

  final public function willBeginExecution() {

    $request = $this->getRequest();

    $user = new PhabricatorUser();

    $phusr = $request->getCookie('phusr');
    $phsid = $request->getCookie('phsid');

    if (strlen($phusr) && $phsid) {
      $info = queryfx_one(
        $user->establishConnection('r'),
        'SELECT u.* FROM %T u JOIN %T s ON u.phid = s.userPHID
          AND s.type LIKE %> AND s.sessionKey = %s',
        $user->getTableName(),
        'phabricator_session',
        'web-',
        $phsid);
      if ($info) {
        $user->loadFromArray($info);
      }
    }

    $translation = $user->getTranslation();
    if ($translation &&
        $translation != PhabricatorEnv::getEnvConfig('translation.provider')) {
      $translation = newv($translation, array());
      PhutilTranslator::getInstance()
        ->setLanguage($translation->getLanguage())
        ->addTranslations($translation->getTranslations());
    }

    $request->setUser($user);

    if ($user->getIsDisabled() && $this->shouldRequireEnabledUser()) {
      $disabled_user_controller = new PhabricatorDisabledUserController(
        $request);
      return $this->delegateToController($disabled_user_controller);
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_CONTROLLER_CHECKREQUEST,
      array(
        'request' => $request,
        'controller' => get_class($this),
      ));
    $event->setUser($user);
    PhutilEventEngine::dispatchEvent($event);
    $checker_controller = $event->getValue('controller');
    if ($checker_controller != get_class($this)) {
      return $this->delegateToController($checker_controller);
    }

    if (PhabricatorEnv::getEnvConfig('darkconsole.enabled')) {
      if ($user->getConsoleEnabled() ||
          PhabricatorEnv::getEnvConfig('darkconsole.always-on')) {
        $console = new DarkConsoleCore();
        $request->getApplicationConfiguration()->setConsole($console);
      }
    }

    if ($this->shouldRequireLogin() && !$user->getPHID()) {
      $login_controller = new PhabricatorLoginController($request);
      return $this->delegateToController($login_controller);
    }

    if ($this->shouldRequireEmailVerification()) {
      $email = $user->loadPrimaryEmail();
      if (!$email) {
        throw new Exception(
          "No primary email address associated with this account!");
      }
      if (!$email->getIsVerified()) {
        $verify_controller = new PhabricatorMustVerifyEmailController($request);
        return $this->delegateToController($verify_controller);
      }
    }

    if ($this->shouldRequireAdmin() && !$user->getIsAdmin()) {
      return new Aphront403Response();
    }

  }

  public function buildStandardPageView() {
    $view = new PhabricatorStandardPageView();
    $view->setRequest($this->getRequest());
    $view->setController($this);

    if ($this->shouldRequireAdmin()) {
      $view->setIsAdminInterface(true);
    }

    return $view;
  }

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();
    $page->appendChild($view);
    $response = new AphrontWebpageResponse();
    $response->setContent($page->render());
    return $response;
  }

  public function getApplicationURI($path = '') {
    if (!$this->getCurrentApplication()) {
      throw new Exception("No application!");
    }
    return $this->getCurrentApplication()->getBaseURI().ltrim($path, '/');
  }

  public function buildApplicationPage($view, array $options) {
    $page = $this->buildStandardPageView();

    $application = $this->getCurrentApplication();
    if ($application) {
      $page->setApplicationName($application->getName());
      $page->setTitle(idx($options, 'title'));
      if ($application->getTitleGlyph()) {
        $page->setGlyph($application->getTitleGlyph());
      }
    }

    if (!($view instanceof AphrontSideNavFilterView)) {
      $nav = new AphrontSideNavFilterView();
      $nav->appendChild($view);
      $view = $nav;
    }

    if ($application) {
      $view->setCurrentApplication($application);
    }

    $view->setUser($this->getRequest()->getUser());
    $view->setFlexNav(true);
    $view->setShowApplicationMenu(true);

    $page->appendChild($view);

    if (idx($options, 'device')) {
      $page->setDeviceReady(true);
      $view->appendChild($page->renderFooter());
    }

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function didProcessRequest($response) {
    $request = $this->getRequest();
    $response->setRequest($request);
    if ($response instanceof AphrontDialogResponse) {
      if (!$request->isAjax()) {
        $view = new PhabricatorStandardPageView();
        $view->setRequest($request);
        $view->setController($this);
        $view->appendChild(
          '<div style="padding: 2em 0;">'.
            $response->buildResponseString().
          '</div>');
        $response = new AphrontWebpageResponse();
        $response->setContent($view->render());
        return $response;
      } else {
        return id(new AphrontAjaxResponse())
          ->setContent(array(
            'dialog' => $response->buildResponseString(),
          ));
      }
    } else if ($response instanceof AphrontRedirectResponse) {
      if ($request->isAjax()) {
        return id(new AphrontAjaxResponse())
          ->setContent(
            array(
              'redirect' => $response->getURI(),
            ));
      }
    }
    return $response;
  }

  protected function getHandle($phid) {
    if (empty($this->handles[$phid])) {
      throw new Exception(
        "Attempting to access handle which wasn't loaded: {$phid}");
    }
    return $this->handles[$phid];
  }

  protected function loadHandles(array $phids) {
    $phids = array_filter($phids);
    $this->handles = $this->loadViewerHandles($phids);
    return $this;
  }

  protected function loadViewerHandles(array $phids) {
    return id(new PhabricatorObjectHandleData($phids))
      ->setViewer($this->getRequest()->getUser())
      ->loadHandles();
  }

  protected function renderHandlesForPHIDs(array $phids) {
    $items = array();
    foreach ($phids as $phid) {
      $items[] = $this->getHandle($phid)->renderLink();
    }
    return implode('<br />', $items);
  }

}
