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
 * @phutil-external-symbol function xhprof_compute_flat_info
 */
class PhabricatorXHProfProfileSymbolView extends AphrontView {

  private $profileData;
  private $baseURI;
  private $symbol;

  public function setProfileData(array $data) {
    $this->profileData = $data;
    return $this;
  }

  public function setBaseURI($uri) {
    $this->baseURI = $uri;
    return $this;
  }

  public function setSymbol($symbol) {
    $this->symbol = $symbol;
    return $this;
  }

  public function render() {
    DarkConsoleXHProfPluginAPI::includeXHProfLib();

    $data = $this->profileData;

    $GLOBALS['display_calls'] = true;
    $totals = array();
    $flat = xhprof_compute_flat_info($data, $totals);
    unset($GLOBALS['display_calls']);

    $symbol = $this->symbol;

    $children = array();
    $parents = array();
    foreach ($this->profileData as $key => $counters) {
      if (strpos($key, '==>') !== false) {
        list($parent, $child) = explode('==>', $key, 2);
      } else {
        continue;
      }
      if ($parent == $symbol) {
        $children[$key] = $child;
      } else if ($child == $symbol) {
        $parents[$key] = $parent;
      }
    }

    $base_uri = $this->baseURI;

    $rows = array();
    $rows[] = array(
      'Metrics for this Call',
      '',
      '',
      '',
    );
    $rows[] = array(
      phutil_render_tag(
        'a',
        array(
          'href' => $base_uri.'?symbol='.$symbol,
        ),
        phutil_escape_html($symbol)),
      $flat[$symbol]['ct'],
      $flat[$symbol]['wt'],
      '100%',
    );

    $rows[] = array(
      'Parent Calls',
      '',
      '',
      '',
    );
    foreach ($parents as $key => $name) {
      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => $base_uri.'?symbol='.$name,
          ),
          phutil_escape_html($name)),
        $data[$key]['ct'],
        $data[$key]['wt'],
        '',
      );
    }


    $rows[] = array(
      'Child Calls',
      '',
      '',
      '',
    );
    $child_rows = array();
    foreach ($children as $key => $name) {
      $child_rows[] = array(
        $name,
        $data[$key]['ct'],
        $data[$key]['wt'],
        $data[$key]['wt'] / $flat[$symbol]['wt'],
      );
    }
    $child_rows = isort($child_rows, 2);
    $child_rows = array_reverse($child_rows);
    $rows = array_merge($rows, $this->formatRows($child_rows));

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Symbol',
        'Count',
        'Wall Time',
        '%',
      ));
    $table->setColumnClasses(
      array(
        'wide pri',
        'n',
        'n',
        'n',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('XHProf Profile');
    $panel->appendChild($table);

    return $panel->render();
  }

  private function formatRows($rows) {
    $base_uri = $this->baseURI;

    $result = array();
    foreach ($rows as $row) {
      $result[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => $base_uri.'?symbol='.$row[0],
          ),
          phutil_escape_html($row[0])),
        number_format($row[1]),
        number_format($row[2]).' us',
        sprintf('%.1f%%', 100 * $row[3]),
      );
    }
    return $result;
  }

}
