<?php

final class DiffusionPatternSearchView extends DiffusionView {

  private $path;
  private $matches;
  private $pattern;

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function setMatches(array $matches) {
    $this->matches = $matches;
    return $this;
  }

  public function setPattern($pattern) {
    $this->pattern = $pattern;
    return $this;
  }

  public function render() {
    $drequest = $this->getDiffusionRequest();
    $path = $this->path;
    $pattern = $this->pattern;
    $rows = array();

    foreach ($this->matches as $result) {
      list($line, $string) = $result;

      $matches = null;
      $count = @preg_match_all(
        '('.$pattern.')u',
        $string,
        $matches,
        PREG_OFFSET_CAPTURE);

      if (!$count) {
        $output = ltrim($string);
      } else {
        $output = array();
        $cursor = 0;
        $length = strlen($string);
        foreach ($matches[0] as $match) {
          $offset = $match[1];
          if ($cursor != $offset) {
            $output[] = array(
              'text' => substr($string, $cursor, $offset),
              'highlight' => false,
            );
          }
          $output[] = array(
            'text' => $match[0],
            'highlight' => true,
          );
          $cursor = $offset + strlen($match[0]);
        }
        if ($cursor != $length) {
          $output[] = array(
            'text' => substr($string, $cursor),
            'highlight' => false,
          );
        }

        if ($output) {
          $output[0]['text'] =  ltrim($output[0]['text']);
        }

        foreach ($output as $key => $segment) {
          if ($segment['highlight']) {
            $output[$key] = phutil_tag('strong', array(), $segment['text']);
          } else {
            $output[$key] = $segment['text'];
          }
        }
      }

      $string = phutil_tag(
        'pre',
        array('class' => 'PhabricatorMonospaced phui-source-fragment'),
        $output);

      $href = $drequest->generateURI(array(
        'action' => 'browse',
        'path' => $path,
        'line' => $line,
      ));

      $rows[] = array(
        phutil_tag('a', array('href' => $href), $line),
        $string,
      );
    }

    $path_title = Filesystem::readablePath($this->path, $drequest->getPath());

    $href = $drequest->generateURI(
      array(
        'action' => 'browse',
        'path' => $this->path,
      ));

    $title = phutil_tag('a', array('href' => $href), $path_title);


    $table = id(new AphrontTableView($rows))
      ->setClassName('remarkup-code')
      ->setHeaders(array(pht('Line'), pht('String')))
      ->setColumnClasses(array('n', 'wide'));

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);

    return $box->render();
  }


}
