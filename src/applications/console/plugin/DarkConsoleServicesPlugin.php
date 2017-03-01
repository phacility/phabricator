<?php

final class DarkConsoleServicesPlugin extends DarkConsolePlugin {

  protected $observations;

  public function getName() {
    return pht('Services');
  }

  public function getDescription() {
    return pht('Information about services.');
  }

  public static function getQueryAnalyzerHeader() {
    return 'X-Phabricator-QueryAnalyzer';
  }

  public static function isQueryAnalyzerRequested() {
    if (!empty($_REQUEST['__analyze__'])) {
      return true;
    }

    $header = AphrontRequest::getHTTPHeader(self::getQueryAnalyzerHeader());
    if ($header) {
      return true;
    }

    return false;
  }

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public function generateData() {
    $should_analyze = self::isQueryAnalyzerRequested();

    $log = PhutilServiceProfiler::getInstance()->getServiceCallLog();
    foreach ($log as $key => $entry) {
      $config = idx($entry, 'config', array());
      unset($log[$key]['config']);

      if (!$should_analyze) {
        $log[$key]['explain'] = array(
          'sev'     => 7,
          'size'    => null,
          'reason'  => pht('Disabled'),
        );
        // Query analysis is disabled for this request, so don't do any of it.
        continue;
      }

      if ($entry['type'] != 'query') {
        continue;
      }

      // For each SELECT query, go issue an EXPLAIN on it so we can flag stuff
      // causing table scans, etc.
      if (preg_match('/^\s*SELECT\b/i', $entry['query'])) {
        $conn = PhabricatorDatabaseRef::newRawConnection($entry['config']);
        try {
          $explain = queryfx_all(
            $conn,
            'EXPLAIN %Q',
            $entry['query']);

          $badness = 0;
          $size    = 1;
          $reason  = null;

          foreach ($explain as $table) {
            $size *= (int)$table['rows'];

            switch ($table['type']) {
              case 'index':
                $cur_badness = 1;
                $cur_reason  = 'Index';
                break;
              case 'const':
                $cur_badness = 1;
                $cur_reason = 'Const';
                break;
              case 'eq_ref';
                $cur_badness = 2;
                $cur_reason = 'EqRef';
                break;
              case 'range':
                $cur_badness = 3;
                $cur_reason = 'Range';
                break;
              case 'ref':
                $cur_badness = 3;
                $cur_reason = 'Ref';
                break;
              case 'fulltext':
                $cur_badness = 3;
                $cur_reason = 'Fulltext';
                break;
              case 'ALL':
                if (preg_match('/Using where/', $table['Extra'])) {
                  if ($table['rows'] < 256 && !empty($table['possible_keys'])) {
                    $cur_badness = 2;
                    $cur_reason = pht('Small Table Scan');
                  } else {
                    $cur_badness = 6;
                    $cur_reason = pht('TABLE SCAN!');
                  }
                } else {
                  $cur_badness = 3;
                  $cur_reason = pht('Whole Table');
                }
                break;
              default:
                if (preg_match('/No tables used/i', $table['Extra'])) {
                  $cur_badness = 1;
                  $cur_reason = pht('No Tables');
                } else if (preg_match('/Impossible/i', $table['Extra'])) {
                  $cur_badness = 1;
                  $cur_reason = pht('Empty');
                } else {
                  $cur_badness = 4;
                  $cur_reason = pht("Can't Analyze");
                }
                break;
            }

            if ($cur_badness > $badness) {
              $badness = $cur_badness;
              $reason = $cur_reason;
            }
          }

          $log[$key]['explain'] = array(
            'sev'     => $badness,
            'size'    => $size,
            'reason'  => $reason,
          );
        } catch (Exception $ex) {
          $log[$key]['explain'] = array(
            'sev'     => 5,
            'size'    => null,
            'reason'  => $ex->getMessage(),
          );
        }
      }
    }

    return array(
      'start' => PhabricatorStartup::getStartTime(),
      'end'   => microtime(true),
      'log'   => $log,
      'analyzeURI' => (string)$this
        ->getRequestURI()
        ->alter('__analyze__', true),
      'didAnalyze' => $should_analyze,
    );
  }

  public function renderPanel() {
    $data = $this->getData();

    $log = $data['log'];
    $results = array();

    $results[] = phutil_tag(
      'div',
      array('class' => 'dark-console-panel-header'),
      array(
        phutil_tag(
          'a',
          array(
            'href'  => $data['analyzeURI'],
            'class' => $data['didAnalyze'] ? 'disabled button' : 'green button',
          ),
          pht('Analyze Query Plans')),
        phutil_tag('h1', array(), pht('Calls to External Services')),
        phutil_tag('div', array('style' => 'clear: both;')),
      ));

    $page_total = $data['end'] - $data['start'];
    $totals = array();
    $counts = array();

    foreach ($log as $row) {
      $totals[$row['type']] = idx($totals, $row['type'], 0) + $row['duration'];
      $counts[$row['type']] = idx($counts, $row['type'], 0) + 1;
    }
    $totals['All Services'] = array_sum($totals);
    $counts['All Services'] = array_sum($counts);

    $totals['Entire Page'] = $page_total;
    $counts['Entire Page'] = 0;

    $summary = array();
    foreach ($totals as $type => $total) {
      $summary[] = array(
        $type,
        number_format($counts[$type]),
        pht('%s us', new PhutilNumber((int)(1000000 * $totals[$type]))),
        sprintf('%.1f%%', 100 * $totals[$type] / $page_total),
      );
    }
    $summary_table = new AphrontTableView($summary);
    $summary_table->setColumnClasses(
      array(
        '',
        'n',
        'n',
        'wide',
      ));
    $summary_table->setHeaders(
      array(
        pht('Type'),
        pht('Count'),
        pht('Total Cost'),
        pht('Page Weight'),
      ));

    $results[] = $summary_table->render();

    $rows = array();
    foreach ($log as $row) {

      $analysis = null;

      switch ($row['type']) {
        case 'query':
          $info = $row['query'];
          $info = wordwrap($info, 128, "\n", true);

          if (!empty($row['explain'])) {
            $analysis = phutil_tag(
              'span',
              array(
                'class' => 'explain-sev-'.$row['explain']['sev'],
              ),
              $row['explain']['reason']);
          }

          break;
        case 'connect':
          $info = $row['host'].':'.$row['database'];
          break;
        case 'exec':
          $info = $row['command'];
          break;
        case 's3':
        case 'conduit':
          $info = $row['method'];
          break;
        case 'http':
          $info = $row['uri'];
          break;
        default:
          $info = '-';
          break;
      }

      $offset = ($row['begin'] - $data['start']);

      $rows[] = array(
        $row['type'],
        pht('+%s ms', new PhutilNumber(1000 * $offset)),
        pht('%s us', new PhutilNumber(1000000 * $row['duration'])),
        $info,
        $analysis,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        null,
        'n',
        'n',
        'wide',
        '',
      ));
    $table->setHeaders(
      array(
        pht('Event'),
        pht('Start'),
        pht('Duration'),
        pht('Details'),
        pht('Analysis'),
      ));

    $results[] = $table->render();

    return phutil_implode_html("\n", $results);
  }
}
