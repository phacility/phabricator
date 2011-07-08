<?php

/*
 * Copyright 2011 Facebook, Inc.
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

final class PhabricatorPHIDConstants {

  const PHID_TYPE_USER    = 'USER';
  const PHID_TYPE_MLST    = 'MLST';
  const PHID_TYPE_DREV    = 'DREV';
  const PHID_TYPE_TASK    = 'TASK';
  const PHID_TYPE_FILE    = 'FILE';
  const PHID_TYPE_PROJ    = 'PROJ';
  const PHID_TYPE_UNKNOWN = '????';
  const PHID_TYPE_MAGIC   = '!!!!';
  const PHID_TYPE_REPO    = 'REPO';
  const PHID_TYPE_CMIT    = 'CMIT';
  const PHID_TYPE_OPKG    = 'OPKG';
  const PHID_TYPE_PSTE    = 'PSTE';
  const PHID_TYPE_STRY    = 'STRY';
  const PHID_TYPE_POLL    = 'POLL';

  public static function getTypes() {
    return array(
      self::PHID_TYPE_USER,
      self::PHID_TYPE_MLST,
      self::PHID_TYPE_DREV,
      self::PHID_TYPE_TASK,
      self::PHID_TYPE_FILE,
      self::PHID_TYPE_PROJ,
      self::PHID_TYPE_UNKNOWN,
      self::PHID_TYPE_MAGIC,
      self::PHID_TYPE_REPO,
      self::PHID_TYPE_CMIT,
      self::PHID_TYPE_PSTE,
      self::PHID_TYPE_OPKG,
      self::PHID_TYPE_STRY,
      self::PHID_TYPE_POLL,
    );
  }
}
