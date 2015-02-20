<?php

final class HarbormasterBuildCommand extends HarbormasterDAO {

  const COMMAND_STOP = 'stop';
  const COMMAND_RESUME = 'resume';
  const COMMAND_RESTART = 'restart';

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
