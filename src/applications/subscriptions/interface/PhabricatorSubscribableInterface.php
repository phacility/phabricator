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

interface PhabricatorSubscribableInterface {

  /**
   * Return true to indicate that the given PHID is automatically subscribed
   * to the object (for example, they are the author or in some other way
   * irrevocably a subscriber). This will, e.g., cause the UI to render
   * "Automatically Subscribed" instead of "Subscribe".
   *
   * @param PHID  PHID (presumably a user) to test for automatic subscription.
   * @return bool True if the object/user is automatically subscribed.
   */
  public function isAutomaticallySubscribed($phid);

}
