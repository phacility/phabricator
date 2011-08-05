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

/**
 * @group markup
 */
abstract class PhabricatorRemarkupRuleObjectHandle
  extends PhutilRemarkupRule {

  const KEY_RULE_HANDLE = 'rule.handle';

  abstract protected function getObjectNamePrefix();
  abstract protected function loadObjectPHID($id);

  public function apply($text) {
    $prefix = $this->getObjectNamePrefix();
    return preg_replace_callback(
      "@\B{{$prefix}(\d+)}\B@",
      array($this, 'markupObjectHandle'),
      $text);
  }

  private function markupObjectHandle($matches) {
    // TODO: These are single gets but should be okay for now, they're behind
    // the cache.
    $phid = $this->loadObjectPHID($matches[1]);
    if (!$phid) {
      return $matches[0];
    }

    $engine = $this->getEngine();
    $token = $engine->storeText('');

    $metadata_key = self::KEY_RULE_HANDLE;
    $metadata = $engine->getTextMetadata($metadata_key, array());
    if (empty($metadata[$phid])) {
      $metadata[$phid] = array();
    }
    $metadata[$phid][] = $token;
    $engine->setTextMetadata($metadata_key, $metadata);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();

    $metadata_key = self::KEY_RULE_HANDLE;
    $metadata = $engine->getTextMetadata($metadata_key, array());
    if (empty($metadata)) {
      return;
    }

    $handles = id(new PhabricatorObjectHandleData(array_keys($metadata)))
      ->loadHandles();

    foreach ($metadata as $phid => $tokens) {
      $link = $handles[$phid]->renderLink();
      foreach ($tokens as $token) {
        $engine->overwriteStoredText($token, $link);
      }
    }

    $engine->setTextMetadata($metadata_key, array());
  }

}
