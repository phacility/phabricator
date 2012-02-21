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

final class PhabricatorOAuthServerScope {

  const SCOPE_OFFLINE_ACCESS = 'offline_access';
  const SCOPE_WHOAMI         = 'whoami';
  const SCOPE_NOT_ACCESSIBLE = 'not_accessible';

  /*
   * Note this does not contain SCOPE_NOT_ACCESSIBLE which is magic
   * used to simplify code for data that is not currently accessible
   * via OAuth.
   */
  static public function getScopesDict() {
    return array(
      self::SCOPE_OFFLINE_ACCESS => 1,
      self::SCOPE_WHOAMI         => 1,
    );
  }

}
