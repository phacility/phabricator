<?php

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
            $href = $user->loadEditorLink($entry['file'], $entry['line'], null);
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
    $table->setHeaders(array(pht('Error')));
    $table->setNoDataString(pht('No errors.'));

    return phutil_tag(
      'div',
      array(),
      array(
        phutil_tag('div', array(), $table->render()),
        phutil_tag('pre', array('class' => 'PhabricatorMonospaced'), $details),
      ));
  }

}
