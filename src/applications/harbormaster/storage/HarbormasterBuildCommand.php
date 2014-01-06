<?php

final class HarbormasterBuildCommand extends HarbormasterDAO {

  const COMMAND_STOP = 'stop';
  const COMMAND_RESUME = 'resume';
  const COMMAND_RESTART = 'restart';

  protected $authorPHID;
  protected $targetPHID;
  protected $command;

}
