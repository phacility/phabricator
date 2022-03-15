<?php
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * Adds the "risk analyzer plugin" JS to the differential view
 */

final class RiskAnalyzerEventListener extends PhabricatorEventListener {

  public function register() {
    if (PhabricatorEnv::getEnvConfig('bugzilla.url') != "http://bmo.test") {
      // Only enable this event listener if we're not running in the local development environment
      $this->listen(PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES);
    }
  }

  public function handleEvent(PhutilEvent $event) {
    if ($event->getType() == PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES) {
      $response = CelerityAPI::getStaticResourceResponse();
      $response->requireResource('moz-risk-analysis-js', 'phabricator');
      $response->addContentSecurityPolicyURI('connect-src', 'https://community-tc.services.mozilla.com');
    }
  }
}
