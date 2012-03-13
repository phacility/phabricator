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

final class PhabricatorFeedStoryData extends PhabricatorFeedDAO {

  protected $phid;

  protected $storyType;
  protected $storyData;
  protected $authorPHID;
  protected $chronologicalKey;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID       => true,
      self::CONFIG_SERIALIZATION  => array(
        'storyData'  => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_STRY);
  }

  public function getEpoch() {
    if (PHP_INT_SIZE < 8) {
      // We're on a 32-bit machine.
      if (function_exists('bcadd')) {
        // Try to use the 'bc' extension.
        return bcdiv($this->chronologicalKey, bcpow(2,32));
      } else {
        // Do the math in MySQL. TODO: If we formalize a bc dependency, get
        // rid of this.
        // See: PhabricatorFeedStoryPublisher::generateChronologicalKey()
        $conn_r = id($this->establishConnection('r'));
        $result = queryfx_one(
          $conn_r,
          // Insert the chronologicalKey as a string since longs don't seem to
          // be supported by qsprintf and ints get maxed on 32 bit machines.
          'SELECT (%s >> 32) as N',
          $this->chronologicalKey);
        return $result['N'];
      }
    } else {
      return $this->chronologicalKey >> 32;
    }
  }

  public function getValue($key, $default = null) {
    return idx($this->storyData, $key, $default);
  }

}
