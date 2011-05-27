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

class DifferentialMarkupEngineFactory {

  public function newDifferentialCommentMarkupEngine() {
    $engine = new PhutilRemarkupEngine();

    $engine->setConfig('preserve-linebreaks', true);
    $engine->setConfig(
      'pygments.enabled',
      PhabricatorEnv::getEnvConfig('pygments.enabled'));

    $rules = array();
    $rules[] = new PhutilRemarkupRuleEscapeRemarkup();
    if (PhabricatorEnv::getEnvConfig('files.enable-proxy')) {
      $rules[] = new PhabricatorRemarkupRuleProxyImage();
    }

    if (PhabricatorEnv::getEnvConfig('remarkup.enable-embedded-youtube')) {
      $rules[] = new PhabricatorRemarkupRuleYoutube();
    }

    $rules[] = new PhutilRemarkupRuleHyperlink();

    $rules[] = new PhabricatorRemarkupRuleDifferential();
    $rules[] = new PhabricatorRemarkupRuleDiffusion();
    $rules[] = new PhabricatorRemarkupRuleManiphest();
    $rules[] = new PhabricatorRemarkupRuleImageMacro();

    $rules[] = new PhutilRemarkupRuleEscapeHTML();
    $rules[] = new PhutilRemarkupRuleMonospace();
    $rules[] = new PhutilRemarkupRuleBold();
    $rules[] = new PhutilRemarkupRuleItalic();

    $blocks = array();
    $blocks[] = new PhutilRemarkupEngineRemarkupQuotesBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupHeaderBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupListBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupCodeBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupDefaultBlockRule();

    foreach ($blocks as $block) {
      if (!($block instanceof PhutilRemarkupEngineRemarkupCodeBlockRule)) {
        $block->setMarkupRules($rules);
      }
    }

    $engine->setBlockRules($blocks);

    return $engine;
  }

}
