<?php

/**
 * @phutil-external-symbol function xhprof_compute_flat_info
 */
final class PhabricatorXHProfProfileTopLevelView
  extends PhabricatorXHProfProfileView {

  private $profileData;
  private $limit;
  private $file;

  public function setProfileData(array $data) {
    $this->profileData = $data;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setFile(PhabricatorFile $file) {
    $this->file = $file;
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
      pht('Total'),
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

    Javelin::initBehavior('phabricator-tooltips');

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Symbol'),
        pht('Count'),
        javelin_tag(
          'span',
          array(
            'sigil' => 'has-tooltip',
            'meta'  => array(
              'tip' => pht(
                'Total wall time spent in this function and all of '.
                'its children (children are other functions it called '.
                'while executing).'),
              'size' => 200,
            ),
          ),
          pht('Wall Time (Inclusive)')),
        '%',
        javelin_tag(
          'span',
          array(
            'sigil' => 'has-tooltip',
            'meta'  => array(
              'tip' => pht(
                'Wall time spent in this function, excluding time '.
                'spent in children (children are other functions it '.
                'called while executing).'),
              'size' => 200,
            ),
          ),
          pht('Wall Time (Exclusive)')),
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

    $panel = new PHUIObjectBoxView();
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('XHProf Profile'));

    if ($this->file) {
      $button = id(new PHUIButtonView())
        ->setHref($this->file->getBestURI())
        ->setText(pht('Download %s Profile', '.xhprof'))
        ->setTag('a');
      $header->addActionLink($button);
    }

    $panel->setHeader($header);
    $panel->setTable($table);

    return $panel->render();
  }
}
