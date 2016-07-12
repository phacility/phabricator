<?php

final class AphrontStackTraceView extends AphrontView {

  private $trace;

  public function setTrace($trace) {
    $this->trace = $trace;
    return $this;
  }

  public function render() {
    $user = $this->getUser();
    $trace = $this->trace;

    $libraries = PhutilBootloader::getInstance()->getAllLibraries();

    // TODO: Make this configurable?
    $path = 'https://secure.phabricator.com/diffusion/%s/browse/master/src/';

    $callsigns = array(
      'arcanist' => 'ARC',
      'phutil' => 'PHU',
      'phabricator' => 'P',
    );

    $rows = array();
    $depth = count($trace);
    foreach ($trace as $part) {
      $lib = null;
      $file = idx($part, 'file');
      $relative = $file;
      foreach ($libraries as $library) {
        $root = phutil_get_library_root($library);
        if (Filesystem::isDescendant($file, $root)) {
          $lib = $library;
          $relative = Filesystem::readablePath($file, $root);
          break;
        }
      }

      $where = '';
      if (isset($part['class'])) {
        $where .= $part['class'].'::';
      }
      if (isset($part['function'])) {
        $where .= $part['function'].'()';
      }

      if ($file) {
        if (isset($callsigns[$lib])) {
          $attrs = array('title' => $file);
          if (empty($attrs['href'])) {
            $attrs['href'] = sprintf($path, $callsigns[$lib]).
              str_replace(DIRECTORY_SEPARATOR, '/', $relative).
              '$'.$part['line'];
            $attrs['target'] = '_blank';
          }
          $file_name = phutil_tag(
            'a',
            $attrs,
            $relative);
        } else {
          $file_name = phutil_tag(
            'span',
            array(
              'title' => $file,
            ),
            $relative);
        }
        $file_name = hsprintf('%s : %d', $file_name, $part['line']);
      } else {
        $file_name = phutil_tag('em', array(), '(Internal)');
      }


      $rows[] = array(
        $depth--,
        $lib,
        $file_name,
        $where,
      );
    }
    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Depth'),
        pht('Library'),
        pht('File'),
        pht('Where'),
      ));
    $table->setColumnClasses(
      array(
        'n',
        '',
        '',
        'wide',
      ));

    return phutil_tag(
      'div',
      array(
        'class' => 'exception-trace',
      ),
      $table->render());
  }

}
