<?php

final class PhabricatorXHPASTParseTree extends PhabricatorXHPASTDAO {

  protected $authorPHID;
  protected $input;
  protected $returnCode;
  protected $stdout;
  protected $stderr;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'authorPHID' => 'phid?',
        'input' => 'text',
        'returnCode' => 'sint32',
        'stdout' => 'text',
        'stderr' => 'text',
      ),
    ) + parent::getConfiguration();
  }
}
