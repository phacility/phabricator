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
final class PhabricatorXHProfProfileTopLevelView
  extends PhabricatorXHProfProfileView {

  private $profileData;
  private $limit;

  public function setProfileData(array $data) {
    $this->profileData = $data;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function render() {
    DarkConsoleXHProfPluginAPI::includeXHProfLib();

    $GLOBALS['display_calls'] = true;
    $totals = array();
    $flat = xhprof_compute_flat_info($this->profileData, $totals);
    unset($GLOBALS['display_calls']);

    $aggregated = array();
    foreach ($flat as $call => $counters) {
      $parts = explode('@', $call, 2);
      $agg_call = reset($parts);
      if (empty($aggregated[$agg_call])) {
        $aggregated[$agg_call] = $counters;
      } else {
        foreach ($aggregated[$agg_call] as $key => $val) {
          if ($key != 'wt') {
            $aggregated[$agg_call][$key] += $counters[$key];
          }
        }
      }
    }
    $flat = $aggregated;

    $flat = isort($flat, 'wt');
    $flat = array_reverse($flat);

    $rows = array();
    $rows[] = array(
      'Total',
      number_format($totals['ct']),
      number_format($totals['wt']).' us',
      '100.0%',
      number_format($totals['wt']).' us',
      '100.0%',
    );

    if ($this->limit) {
      $flat = array_slice($flat, 0, $this->limit);
    }

    foreach ($flat as $call => $counters) {
      $rows[] = array(
        $this->renderSymbolLink($call),
        number_format($counters['ct']),
        number_format($counters['wt']).' us',
        sprintf('%.1f%%', 100 * $counters['wt'] / $totals['wt']),
        number_format($counters['excl_wt']).' us',
        sprintf('%.1f%%', 100 * $counters['excl_wt'] / $totals['wt']),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Symbol',
        'Count',
        'Incl Wall Time',
        '%',
        'Excl Wall Time',
        '%',
      ));
    $table->setColumnClasses(
      array(
        'wide pri',
        'n',
        'n',
        'n',
        'n',
        'n',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('XHProf Profile');
    $panel->appendChild($table);

    return $panel->render();
  }
}
