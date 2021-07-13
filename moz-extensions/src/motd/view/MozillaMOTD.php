<?php
/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 *
 * This Source Code Form is "Incompatible With Secondary Licenses", as
 * defined by the Mozilla Public License, v. 2.0.
 */

class MozillaMOTD extends Phobject {
  private $infoview;

  public function __construct() {
    $title = PhabricatorEnv::getEnvConfig('moz-motd.title');
    $message = PhabricatorEnv::getEnvConfig('moz-motd.message');
    if (!$title) {
      return;
    }
    $this->infoview = new PHUIInfoView();
    $this->infoview->setSeverity(PHUIInfoView::SEVERITY_WARNING);
    $this->infoview->setTitle($title);
    $this->infoview->appendChild(hsprintf($message));
  }

  public function render() {
    if (!$this->infoview) {
      return;
    }
    return $this->infoview->render();
  }
}
