<?php

/**
 * @group console
 */
final class DarkConsoleServicesPlugin extends DarkConsolePlugin {

  protected $observations;

  public function getName() {
    return 'Services';
  }

  public function getDescription() {
    return 'Information about services.';
  }

  public function generateData() {

    $log = PhutilServiceProfiler::getInstance()->getServiceCallLog();
    foreach ($log as $key => $entry) {
      $config = idx($entry, 'config', array());
      unset($log[$key]['config']);

      if (empty($_REQUEST['__analyze__'])) {
        $log[$key]['explain'] = array(
          'sev'     => 7,
          'size'    => null,
          'reason'  => 'Disabled',
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
        $conn = PhabricatorEnv::newObjectFromConfig(
          'mysql.implementation',
          array($entry['config']));
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
                    $cur_reason = 'Small Table Scan';
                  } else {
                    $cur_badness = 6;
                    $cur_reason = 'TABLE SCAN!';
                  }
                } else {
                  $cur_badness = 3;
                  $cur_reason = 'Whole Table';
                }
                break;
              default:
                if (preg_match('/No tables used/i', $table['Extra'])) {
                  $cur_badness = 1;
                  $cur_reason = 'No Tables';
                } else if (preg_match('/Impossible/i', $table['Extra'])) {
                  $cur_badness = 1;
                  $cur_reason = 'Empty';
                } else {
                  $cur_badness = 4;
                  $cur_reason = "Can't Analyze";
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
      'start' => $GLOBALS['__start__'],
      'end'   => microtime(true),
      'log'   => $log,
    );
  }

  public function render() {
    $data = $this->getData();
    $log = $data['log'];
    $results = array();

    $results[] =
      '<div class="dark-console-panel-header">'.
        phutil_render_tag(
          'a',
          array(
            'href'  => $this->getRequestURI()->alter('__analyze__', true),
            'class' => isset($_REQUEST['__analyze__'])
              ? 'disabled button'
              : 'green button',
          ),
          'Analyze Query Plans').
        '<h1>Calls to External Services</h1>'.
        '<div style="clear: both;"></div>'.
      '</div>';

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
        number_format((int)(1000000 * $totals[$type])).' us',
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
        'Type',
        'Count',
        'Total Cost',
        'Page Weight',
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
            $analysis = phutil_escape_html($row['explain']['reason']);
            $analysis = phutil_render_tag(
              'span',
              array(
                'class' => 'explain-sev-'.$row['explain']['sev'],
              ),
              $analysis);
          }

          $info = phutil_escape_html($info);
          break;
        case 'connect':
          $info = $row['host'].':'.$row['database'];
          $info = phutil_escape_html($info);
          break;
        case 'exec':
          $info = $row['command'];
          $info = phutil_escape_html($info);
          break;
        case 'conduit':
          $info = $row['method'];
          $info = phutil_escape_html($info);
          break;
        case 'http':
          $info = $row['uri'];
          $info = phutil_escape_html($info);
          break;
        default:
          $info = '-';
          break;
      }

      $rows[] = array(
        phutil_escape_html($row['type']),
        '+'.number_format(1000 * ($row['begin'] - $data['start'])).' ms',
        number_format(1000000 * $row['duration']).' us',
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
        'Event',
        'Start',
        'Duration',
        'Details',
        'Analysis',
      ));

    $results[] = $table->render();

    return implode("\n", $results);
  }
}

