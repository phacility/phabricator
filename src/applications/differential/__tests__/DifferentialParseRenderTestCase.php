<?php

final class DifferentialParseRenderTestCase extends PhabricatorTestCase {

  private function getTestDataDirectory() {
    return dirname(__FILE__).'/data/';
  }

  public function testParseRender() {
    $dir = $this->getTestDataDirectory();
    foreach (Filesystem::listDirectory($dir, $show_hidden = false) as $file) {
      if (!preg_match('/\.diff$/', $file)) {
        continue;
      }
      $data = Filesystem::readFile($dir.$file);

      // Strip trailing "~" characters from inputs so they may contain
      // trailing whitespace.
      $data = preg_replace('/~$/m', '', $data);

      $opt_file = $dir.$file.'.options';
      if (Filesystem::pathExists($opt_file)) {
        $options = Filesystem::readFile($opt_file);
        try {
          $options = phutil_json_decode($options);
        } catch (PhutilJSONParserException $ex) {
          throw new PhutilProxyException(
            pht('Invalid options file: %s.', $opt_file),
            $ex);
        }
      } else {
        $options = array();
      }

      foreach (array('one', 'two') as $type) {
        $this->runParser($type, $data, $file, 'expect');
        $this->runParser($type, $data, $file, 'unshielded');
      }
    }
  }

  private function runParser($type, $data, $file, $extension) {
    $dir = $this->getTestDataDirectory();
    $test_file = $dir.$file.'.'.$type.'.'.$extension;
    if (!Filesystem::pathExists($test_file)) {
      return;
    }

    $unshielded = false;
    switch ($extension) {
      case 'unshielded':
        $unshielded = true;
        break;
    }

    $parsers = $this->buildChangesetParsers($type, $data, $file);
    $actual = $this->renderParsers($parsers, $unshielded);
    $expect = Filesystem::readFile($test_file);

    $this->assertEqual($expect, $actual, basename($test_file));
  }

  private function renderParsers(array $parsers, $unshield) {
    $result = array();
    foreach ($parsers as $parser) {
      if ($unshield) {
        $s_range = 0;
        $e_range = 0xFFFF;
      } else {
        $s_range = null;
        $e_range = null;
      }

      $result[] = $parser->render($s_range, $e_range, array());
    }
    return implode(str_repeat('~', 80)."\n", $result);
  }

  private function buildChangesetParsers($type, $data, $file) {
    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($data);

    $diff = DifferentialDiff::newFromRawChanges(
      PhabricatorUser::getOmnipotentUser(),
      $changes);

    $changesets = $diff->getChangesets();

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer(new PhabricatorUser());

    $viewstate = new PhabricatorChangesetViewState();

    $parsers = array();
    foreach ($changesets as $changeset) {
      $cparser = id(new DifferentialChangesetParser())
        ->setViewer(new PhabricatorUser())
        ->setDisableCache(true)
        ->setChangeset($changeset)
        ->setMarkupEngine($engine)
        ->setViewState($viewstate);

      if ($type == 'one') {
        $cparser->setRenderer(new DifferentialChangesetOneUpTestRenderer());
      } else if ($type == 'two') {
        $cparser->setRenderer(new DifferentialChangesetTwoUpTestRenderer());
      } else {
        throw new Exception(pht('Unknown renderer type "%s"!', $type));
      }

      $parsers[] = $cparser;
    }

    return $parsers;
  }

}
