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

final class PhabricatorFeedStoryTypeConstants
  extends PhabricatorFeedConstants {

  const STORY_STATUS        = 'PhabricatorFeedStoryStatus';
  const STORY_DIFFERENTIAL  = 'PhabricatorFeedStoryDifferential';
  const STORY_PHRICTION     = 'PhabricatorFeedStoryPhriction';
  const STORY_MANIPHEST     = 'PhabricatorFeedStoryManiphest';
  const STORY_PROJECT       = 'PhabricatorFeedStoryProject';
  const STORY_AUDIT         = 'PhabricatorFeedStoryAudit';
  const STORY_COMMIT        = 'PhabricatorFeedStoryCommit';

}
