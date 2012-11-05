<?php

/**
 * @group console
 */
final class DarkConsoleErrorLogPlugin extends DarkConsolePlugin {

  public function getName() {
    $count = count($this->getData());

    if ($count) {
      return
        '<span style="color: #ff0000;">&bull;</span> '.
        "Error Log ({$count})";
    }

    return 'Error Log';
  }


  public function getDescription() {
    return 'Shows errors and warnings.';
  }


  public function generateData() {
    return DarkConsoleErrorLogPluginAPI::getErrors();
  }


  public function render() {

    $data = $this->getData();

    $rows = array();
    $details = '';

    foreach ($data as $index => $row) {
      $file = $row['file'];
      $line = $row['line'];

      $tag = phutil_render_tag(
        'a',
        array(
          'onclick' => jsprintf('show_details(%d)', $index),
        ),
        phutil_escape_html($row['str'].' at ['.basename($file).':'.$line.']'));
      $rows[] = array($tag);

      $details .=
        '<div class="dark-console-panel-error-details" id="row-details-'.
        $index.'">'.
        phutil_escape_html($row['details'])."\n".
        'Stack trace:'."\n";

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

        $details .= phutil_render_tag(
          'a',
          array(
            'href' => $href,
          ),
          phutil_escape_html($line));
        $details .= "\n";
      }

      $details .= '</div>';
    }

    $table = new AphrontTableView($rows);
    $table->setClassName('error-log');
    $table->setHeaders(array('Error'));
    $table->setNoDataString('No errors.');

    return '<div>'.
      '<div>'.$table->render().'</div>'.
      '<pre class="PhabricatorMonospaced">'.
      $details.'</pre>'.
      '</div>';
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
