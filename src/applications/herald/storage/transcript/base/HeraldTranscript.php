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

class HeraldTranscript extends HeraldDAO {

  protected $id;
  protected $fbid;

  protected $objectTranscript;
  protected $ruleTranscripts = array();
  protected $conditionTranscripts = array();
  protected $applyTranscripts = array();

  protected $time;
  protected $host;
  protected $path;
  protected $duration;

  protected $objectID;
  protected $dryRun;

  public function getXHeraldRulesHeader() {
    $ids = array();
    foreach ($this->applyTranscripts as $xscript) {
      if ($xscript->getApplied()) {
        if ($xscript->getRuleID()) {
          $ids[] = $xscript->getRuleID();
        }
      }
    }
    if (!$ids) {
      return 'none';
    }

    // A rule may have multiple effects, which will cause it to be listed
    // multiple times.
    $ids = array_unique($ids);

    foreach ($ids as $k => $id) {
      $ids[$k] = '<'.$id.'>';
    }

    return implode(', ', $ids);
  }

  protected function getConfiguration() {
    // Ugh. Too much of a mess to deal with.
    return array(
      self::CONFIG_AUX_FBID     => 'HERALD_TRANSCRIPT',
      self::CONFIG_SERIALIZATION => array(
        'objectTranscript'      => self::SERIALIZATION_PHP,
        'ruleTranscripts'       => self::SERIALIZATION_PHP,
        'conditionTranscripts'  => self::SERIALIZATION_PHP,
        'applyTranscripts'      => self::SERIALIZATION_PHP,
      ),
    ) + parent::getConfiguration();
  }

  public function __construct() {
    $this->time = time();
    $this->host = php_uname('n');
    $this->path = realpath($_SERVER['PHP_ROOT']);
  }

  public function addApplyTranscript(HeraldApplyTranscript $transcript) {
    $this->applyTranscripts[] = $transcript;
    return $this;
  }

  public function getApplyTranscripts() {
    return nonempty($this->applyTranscripts, array());
  }

  public function setDuration($duration) {
    $this->duration = $duration;
    return $this;
  }

  public function setObjectTranscript(HeraldObjectTranscript $transcript) {
    $this->objectTranscript = $transcript;
    return $this;
  }

  public function getObjectTranscript() {
    return $this->objectTranscript;
  }

  public function addRuleTranscript(HeraldRuleTranscript $transcript) {
    $this->ruleTranscripts[$transcript->getRuleID()] = $transcript;
    return $this;
  }

  public function discardDetails() {
    $this->applyTranscripts = null;
    $this->ruleTranscripts = null;
    $this->objectTranscript = null;
    $this->conditionTranscripts = null;
  }

  public function getRuleTranscripts() {
    return nonempty($this->ruleTranscripts, array());
  }

  public function addConditionTranscript(
    HeraldConditionTranscript $transcript) {
    $rule_id = $transcript->getRuleID();
    $cond_id = $transcript->getConditionID();

    $this->conditionTranscripts[$rule_id][$cond_id] = $transcript;
    return $this;
  }

  public function getConditionTranscriptsForRule($rule_id) {
    return idx($this->conditionTranscripts, $rule_id, array());
  }

  public function getMetadataMap() {
    return array(
      'Run At Epoch' => date('F jS, g:i A', $this->time),
      'Run On Host'  => $this->host.':'.$this->path,
      'Run Duration' => (int)(1000 * $this->duration).' ms',
    );
  }

  public function getURI() {
    return 'http://tools.facebook.com/herald/transcript/'.$this->getID().'/';
  }

}
