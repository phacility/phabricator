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

final class MetaMTANotificationType
  extends MetaMTAConstants {

  const TYPE_DIFFERENTIAL_COMMITTED   = 'differential-committed';
  const TYPE_DIFFERENTIAL_CC          = 'differential-cc';
  const TYPE_DIFFERENTIAL_COMMENT     = 'differential-comment';

  const TYPE_MANIPHEST_PROJECTS       = 'maniphest-projects';
  const TYPE_MANIPHEST_PRIORITY       = 'maniphest-priority';
  const TYPE_MANIPHEST_CC             = 'maniphest-cc';
  const TYPE_MANIPHEST_OTHER          = 'maniphest-other';
  const TYPE_MANIPHEST_COMMENT        = 'maniphest-comment';

}
