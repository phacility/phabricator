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

final class PhabricatorSettingsMainController
  extends PhabricatorController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = idx($data, 'key');
  }

  public function processRequest() {
    $request = $this->getRequest();

    $panels = $this->buildPanels();
    $nav = $this->renderSideNav($panels);

    $key = $nav->selectFilter($this->key, head($panels)->getPanelKey());


    $panel = $panels[$key];

    $response = $panel->processRequest($request);
    if ($response instanceof AphrontResponse) {
      return $response;
    }

    $nav->appendChild($response);
    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $panel->getPanelName(),
      ));
  }

  private function buildPanels() {
    $panel_specs = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorSettingsPanel')
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();

    $panels = array();
    foreach ($panel_specs as $spec) {
      $class = newv($spec['name'], array());
      $panels[] = $class->buildPanels();
    }

    $panels = array_mergev($panels);
    $panels = mpull($panels, null, 'getPanelKey');

    $result = array();
    foreach ($panels as $key => $panel) {
      if (!$panel->isEnabled()) {
        continue;
      }
      if (!empty($result[$key])) {
        throw new Exception(
          "Two settings panels share the same panel key ('{$key}'): ".
          get_class($panel).', '.get_class($result[$key]).'.');
      }
      $result[$key] = $panel;
    }

    $result = msort($result, 'getPanelSortKey');

    return $result;
  }

  private function renderSideNav(array $panels) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI('/panel/')));

    $group = null;
    foreach ($panels as $panel) {
      if ($panel->getPanelGroup() != $group) {
        if ($group !== null) {
          $nav->addSpacer();
        }
        $group = $panel->getPanelGroup();
        $nav->addLabel($group);
      }

      $nav->addFilter($panel->getPanelKey(), $panel->getPanelName());
    }

    return $nav;
  }

}
