<?php

final class DifferentialParseRenderTestCase extends PhabricatorTestCase {

  public function testParseRender() {
    $dir = dirname(__FILE__).'/data/';
    foreach (Filesystem::listDirectory($dir, $show_hidden = false) as $file) {
      if (!preg_match('/\.diff$/', $file)) {
        continue;
      }
      $data = Filesystem::readFile($dir.$file);

      $opt_file = $dir.$file.'.options';
      if (Filesystem::pathExists($opt_file)) {
        $options = Filesystem::readFile($opt_file);
        $options = json_decode($options, true);
        if (!is_array($options)) {
          throw new Exception("Invalid options file: {$opt_file}.");
        }
      } else {
        $options = array();
      }

      foreach (array('one', 'two') as $type) {
        $parser = $this->buildChangesetParser($type, $data, $file);
        $actual = $parser->render(null, null, array());

        $expect = Filesystem::readFile($dir.$file.'.'.$type.'.expect');
        $this->assertEqual($expect, (string)$actual, $file.'.'.$type);
      }
    }
  }

  private function buildChangesetParser($type, $data, $file) {
    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($data);

    $diff = DifferentialDiff::newFromRawChanges($changes);
    if (count($diff->getChangesets()) !== 1) {
      throw new Exception("Expected one changeset: {$file}");
    }

    $changeset = head($diff->getChangesets());

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer(new PhabricatorUser());

    $cparser = new DifferentialChangesetParser();
    $cparser->setDisableCache(true);
    $cparser->setChangeset($changeset);
    $cparser->setMarkupEngine($engine);

    if ($type == 'one') {
      $cparser->setRenderer(new DifferentialChangesetOneUpTestRenderer());
    } else if ($type == 'two') {
      $cparser->setRenderer(new DifferentialChangesetTwoUpTestRenderer());
    } else {
      throw new Exception("Unknown renderer type '{$type}'!");
    }

    return $cparser;
  }

}
