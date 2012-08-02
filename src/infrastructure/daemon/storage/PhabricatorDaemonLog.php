<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorDaemonLog extends PhabricatorDaemonDAO {

  const STATUS_UNKNOWN = 'unknown';
  const STATUS_RUNNING = 'run';
  const STATUS_DEAD    = 'dead';
  const STATUS_EXITED  = 'exit';

  protected $daemon;
  protected $host;
  protected $pid;
  protected $argv;
  protected $status;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'argv' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

}
