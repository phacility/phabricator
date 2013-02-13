<?php

/**
 * @group console
 */
final class DarkConsoleErrorLogPlugin extends DarkConsolePlugin {

  public function getName() {
    $count = count($this->getData());
    if ($count) {
      return pht('Error Log (%d)', $count);
    }
    return pht('Error Log');
  }

  public function getOrder() {
    return 0;
  }

  public function getColor() {
    if (count($this->getData())) {
      return '#ff0000';
    }
    return null;
  }

  public function getDescription() {
    return pht('Shows errors and warnings.');
  }

  public function generateData() {
    return DarkConsoleErrorLogPluginAPI::getErrors();
  }

  public function renderPanel() {
    $data = $this->getData();

    $rows = array();
    $details = array();

    foreach ($data as $index => $row) {
      $file = $row['file'];
      $line = $row['line'];

      $tag = phutil_tag(
        'a',
        array(
          'onclick' => jsprintf('show_details(%d)', $index),
        ),
        $row['str'].' at ['.basename($file).':'.$line.']');
      $rows[] = array($tag);

      $details[] = hsprintf(
        '<div class="dark-console-panel-error-details" id="row-details-%s">'.
        "%s\nStack trace:\n",
        $index,
        $row['details']);

      foreach ($row['trace'] as $key => $entry) {
        $line = '';
        if (isset($entry['class'])) {
          $line .= $entry['class'].'::';
        }
        $line .= idx($entry, 'function', '');
        $href = null;
        if (isset($entry['file'])) {
          $line .= ' called at ['.$entry['file'].':'.$entry['line'].']';
          try {
            $user = $this->getRequest()->getUser();
            $href = $user->loadEditorLink($entry['file'], $entry['line'], '');
          } catch (Exception $ex) {
            // The database can be inaccessible.
          }
        }

        $details[] = phutil_tag(
          'a',
          array(
            'href' => $href,
          ),
          $line);
        $details[] = "\n";
      }

      $details[] = hsprintf('</div>');
    }

    $table = new AphrontTableView($rows);
    $table->setClassName('error-log');
    $table->setHeaders(array('Error'));
    $table->setNoDataString('No errors.');

    return hsprintf(
      '<div>'.
        '<div>%s</div>'.
        '<pre class="PhabricatorMonospaced">%s</pre>'.
      '</div>',
      $table->render(),
      phutil_implode_html('', $details));
  }
}

/*
    $data = $this->getData();
    if (!$data) {
      return
        <x:frag>
          <div class="mu">No errors.</div>
        </x:frag>;
    }

    $markup = <table class="LConsoleErrors" />;
    $alt = false;
    foreach ($data as $error) {
      $row = <tr class={$alt ? 'alt' : null} />;

      $text = $error['error'];
      $text = preg_replace('/\(in .* on line \d+\)$/', '', trim($text));

      $trace = $error['trace'];
      $trace = explode("\n", $trace);
      if (!$trace) {
        $trace = array('unknown@0@unknown');
      }

      foreach ($trace as $idx => $traceline) {
        list($file, $line, $where) = array_merge(
          explode('@', $traceline),
          array('?', '?', '?'));
        if ($where == 'DarkConsole->addError' ||
            $where == 'debug_rlog') {
          unset($trace[$idx]);
        }
      }

      $row->appendChild(<th rowspan={count($trace)}>{$text}</th>);

      foreach ($trace as $traceline) {
        list($file, $line, $where) = array_merge(
          explode('@', $traceline),
          array('?', '?', '?'));
        $row->appendChild(<td>{$file}:{$line}</td>);
        $row->appendChild(<td>{$where}()</td>);
        $markup->appendChild($row);
        $row = <tr class={$alt ? 'alt' : null} />;
      }

      $alt = !$alt;
    }

    return
      <x:frag>
        <h1>Errors</h1>
        <div class="LConsoleErrors">{$markup}</div>
      </x:frag>;
*/
