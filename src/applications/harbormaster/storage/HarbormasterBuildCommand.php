<?php

final class HarbormasterBuildCommand extends HarbormasterDAO {

  const COMMAND_PAUSE = 'pause';
  const COMMAND_RESUME = 'resume';
  const COMMAND_RESTART = 'restart';
  const COMMAND_ABORT = 'abort';

  protected $authorPHID;
  protected $targetPHID;
  protected $command;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'command' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_target' => array(
          'columns' => array('targetPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
