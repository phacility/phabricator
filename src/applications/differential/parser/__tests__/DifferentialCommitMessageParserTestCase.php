<?php

final class DifferentialCommitMessageParserTestCase
  extends PhabricatorTestCase {

  public function testDifferentialCommitMessageParser() {
    $dir = dirname(__FILE__).'/messages/';
    $list = Filesystem::listDirectory($dir, $include_hidden = false);
    foreach ($list as $file) {
      if (!preg_match('/.txt$/', $file)) {
        continue;
      }

      $data = Filesystem::readFile($dir.$file);
      $divider = "~~~~~~~~~~\n";
      $parts = explode($divider, $data);
      if (count($parts) !== 4) {
        throw new Exception(
          pht(
            'Expected test file "%s" to contain four parts (message, fields, '.
            'output, errors) divided by "%s".',
            $file,
            '~~~~~~~~~~'));
      }

      list($message, $fields, $output, $errors) = $parts;
      $fields = phutil_json_decode($fields);
      $output = phutil_json_decode($output);
      $errors = phutil_json_decode($errors);

      $parser = id(new DifferentialCommitMessageParser())
        ->setLabelMap($fields)
        ->setTitleKey('title')
        ->setSummaryKey('summary');

      $result_output = $parser->parseCorpus($message);
      $result_errors = $parser->getErrors();

      $this->assertEqual($output, $result_output);
      $this->assertEqual($errors, $result_errors);
    }
  }


  public function testDifferentialCommitMessageFieldParser() {
    $message = <<<EOMESSAGE
This is the title.

Summary: This is the summary.
EOMESSAGE;

    $fields = array(
      new DifferentialTitleCommitMessageField(),
      new DifferentialSummaryCommitMessageField(),
    );

    $expect = array(
      DifferentialTitleCommitMessageField::FIELDKEY =>
        'This is the title.',
      DifferentialSummaryCommitMessageField::FIELDKEY =>
        'This is the summary.',
    );

    $parser = id(new DifferentialCommitMessageParser())
      ->setCommitMessageFields($fields)
      ->setTitleKey(DifferentialTitleCommitMessageField::FIELDKEY)
      ->setSummaryKey(DifferentialSummaryCommitMessageField::FIELDKEY);

    $actual = $parser->parseFields($message);

    $this->assertEqual($expect, $actual);
  }

  public function testDifferentialCommitMessageParserNormalization() {
    $map = array(
      'Test Plan' => 'test plan',
      'REVIEWERS' => 'reviewers',
      'sUmmArY'   => 'summary',
    );

    foreach ($map as $input => $expect) {
      $this->assertEqual(
        $expect,
        DifferentialCommitMessageParser::normalizeFieldLabel($input),
        pht('Field normalization of label "%s".', $input));
    }
  }

}
