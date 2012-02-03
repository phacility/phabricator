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

final class ConduitAPI_remarkup_process_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return 'Process text through remarkup in phabricator context.';
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-NO-CONTENT' => 'Content may not be empty.',
      'ERR-INVALID-ENGINE' => 'Invalid markup engine.',
    );
  }

  public function defineParamTypes() {
    $available_contexts = array_keys($this->getEngineContexts());
    $available_contexts = implode(', ', $available_contexts);

    return array(
      'context' => 'required enum<'.$available_contexts.'>',
      'content' => 'required string',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $content = $request->getValue('content');
    $context = $request->getValue('context');

    $engine_class = idx($this->getEngineContexts(), $context);
    if (!$engine_class) {
      throw new ConduitException('ERR-INVALID_ENGINE');
    }

    $engine = PhabricatorMarkupEngine::$engine_class();

    $result = array(
      'content' => $engine->markupText($content),
    );

    return $result;
  }

  private function getEngineContexts() {
    return array(
      'phriction' => 'newPhrictionMarkupEngine',
      'maniphest' => 'newManiphestMarkupEngine',
      'differential' => 'newDifferentialMarkupEngine',
    );
  }
}
