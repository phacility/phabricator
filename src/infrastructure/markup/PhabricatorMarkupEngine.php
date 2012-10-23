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

/**
 * Manages markup engine selection, configuration, application, caching and
 * pipelining.
 *
 * @{class:PhabricatorMarkupEngine} can be used to render objects which
 * implement @{interface:PhabricatorMarkupInterface} in a batched, cache-aware
 * way. For example, if you have a list of comments written in remarkup (and
 * the objects implement the correct interface) you can render them by first
 * building an engine and adding the fields with @{method:addObject}.
 *
 *   $field  = 'field:body'; // Field you want to render. Each object exposes
 *                           // one or more fields of markup.
 *
 *   $engine = new PhabricatorMarkupEngine();
 *   foreach ($comments as $comment) {
 *     $engine->addObject($comment, $field);
 *   }
 *
 * Now, call @{method:process} to perform the actual cache/rendering
 * step. This is a heavyweight call which does batched data access and
 * transforms the markup into output.
 *
 *   $engine->process();
 *
 * Finally, do something with the results:
 *
 *   $results = array();
 *   foreach ($comments as $comment) {
 *     $results[] = $engine->getOutput($comment, $field);
 *   }
 *
 * If you have a single object to render, you can use the convenience method
 * @{method:renderOneObject}.
 *
 * @task markup Markup Pipeline
 * @task engine Engine Construction
 */
final class PhabricatorMarkupEngine {

  private $objects = array();
  private $viewer;
  private $version = 0;


/* -(  Markup Pipeline  )---------------------------------------------------- */


  /**
   * Convenience method for pushing a single object through the markup
   * pipeline.
   *
   * @param PhabricatorMarkupInterface  The object to render.
   * @param string                      The field to render.
   * @param PhabricatorUser             User viewing the markup.
   * @return string                     Marked up output.
   * @task markup
   */
  public static function renderOneObject(
    PhabricatorMarkupInterface $object,
    $field,
    PhabricatorUser $viewer) {
    return id(new PhabricatorMarkupEngine())
      ->setViewer($viewer)
      ->addObject($object, $field)
      ->process()
      ->getOutput($object, $field);
  }


  /**
   * Queue an object for markup generation when @{method:process} is
   * called. You can retrieve the output later with @{method:getOutput}.
   *
   * @param PhabricatorMarkupInterface  The object to render.
   * @param string                      The field to render.
   * @return this
   * @task markup
   */
  public function addObject(PhabricatorMarkupInterface $object, $field) {
    $key = $this->getMarkupFieldKey($object, $field);
    $this->objects[$key] = array(
      'object' => $object,
      'field'  => $field,
    );

    return $this;
  }


  /**
   * Process objects queued with @{method:addObject}. You can then retrieve
   * the output with @{method:getOutput}.
   *
   * @return this
   * @task markup
   */
  public function process() {
    $keys = array();
    foreach ($this->objects as $key => $info) {
      if (!isset($info['markup'])) {
        $keys[] = $key;
      }
    }

    if (!$keys) {
      return;
    }

    $objects = array_select_keys($this->objects, $keys);

    // Build all the markup engines. We need an engine for each field whether
    // we have a cache or not, since we still need to postprocess the cache.
    $engines = array();
    foreach ($objects as $key => $info) {
      $engines[$key] = $info['object']->newMarkupEngine($info['field']);
      $engines[$key]->setConfig('viewer', $this->viewer);
    }

    // Load or build the preprocessor caches.
    $blocks = $this->loadPreprocessorCaches($engines, $objects);

    // Finalize the output.
    foreach ($objects as $key => $info) {
      $data = $blocks[$key]->getCacheData();
      $engine = $engines[$key];
      $field = $info['field'];
      $object = $info['object'];

      $output = $engine->postprocessText($data);
      $output = $object->didMarkupText($field, $output, $engine);
      $this->objects[$key]['output'] = $output;
    }

    return $this;
  }


  /**
   * Get the output of markup processing for a field queued with
   * @{method:addObject}. Before you can call this method, you must call
   * @{method:process}.
   *
   * @param PhabricatorMarkupInterface  The object to retrieve.
   * @param string                      The field to retrieve.
   * @return string                     Processed output.
   * @task markup
   */
  public function getOutput(PhabricatorMarkupInterface $object, $field) {
    $key = $this->getMarkupFieldKey($object, $field);

    if (empty($this->objects[$key])) {
      throw new Exception(
        "Call addObject() before getOutput() (key = '{$key}').");
    }

    if (!isset($this->objects[$key]['output'])) {
      throw new Exception(
        "Call process() before getOutput().");
    }

    return $this->objects[$key]['output'];
  }


  /**
   * @task markup
   */
  private function getMarkupFieldKey(
    PhabricatorMarkupInterface $object,
    $field) {
    return $object->getMarkupFieldKey($field).'@'.$this->version;
  }


  /**
   * @task markup
   */
  private function loadPreprocessorCaches(array $engines, array $objects) {
    $blocks = array();

    $use_cache = array();
    foreach ($objects as $key => $info) {
      if ($info['object']->shouldUseMarkupCache($info['field'])) {
        $use_cache[$key] = true;
      }
    }

    if ($use_cache) {
      $blocks = id(new PhabricatorMarkupCache())->loadAllWhere(
        'cacheKey IN (%Ls)',
        array_keys($use_cache));
      $blocks = mpull($blocks, null, 'getCacheKey');
    }

    foreach ($objects as $key => $info) {
      if (isset($blocks[$key])) {
        // If we already have a preprocessing cache, we don't need to rebuild
        // it.
        continue;
      }

      $text = $info['object']->getMarkupText($info['field']);
      $data = $engines[$key]->preprocessText($text);

      // NOTE: This is just debugging information to help sort out cache issues.
      // If one machine is misconfigured and poisoning caches you can use this
      // field to hunt it down.

      $metadata = array(
        'host' => php_uname('n'),
      );

      $blocks[$key] = id(new PhabricatorMarkupCache())
        ->setCacheKey($key)
        ->setCacheData($data)
        ->setMetadata($metadata);

      if (isset($use_cache[$key])) {
        // This is just filling a cache and always safe, even on a read pathway.
        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
          try {
            $blocks[$key]->save();
          } catch (AphrontQueryDuplicateKeyException $ex) {
            // Ignore this, we just raced to write the cache.
          }
        unset($unguarded);
      }
    }

    return $blocks;
  }


  /**
   * Set the viewing user. Used to implement object permissions.
   *
   * @param PhabricatorUser The viewing user.
   * @return this
   * @task markup
   */
  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }


/* -(  Engine Construction  )------------------------------------------------ */



  /**
   * @task engine
   */
  public static function newManiphestMarkupEngine() {
    return self::newMarkupEngine(array(
    ));
  }


  /**
   * @task engine
   */
  public static function newPhrictionMarkupEngine() {
    return self::newMarkupEngine(array(
      'header.generate-toc' => true,
    ));
  }


  /**
   * @task engine
   */
  public static function newPhameMarkupEngine() {
    return self::newMarkupEngine(array(
      'macros' => false,
    ));
  }


  /**
   * @task engine
   */
  public static function newFeedMarkupEngine() {
    return self::newMarkupEngine(
      array(
        'macros'      => false,
        'fileproxy'   => false,
        'youtube'     => false,

      ));
  }


  /**
   * @task engine
   */
  public static function newDifferentialMarkupEngine(array $options = array()) {
    return self::newMarkupEngine(array(
      'custom-inline' => PhabricatorEnv::getEnvConfig(
        'differential.custom-remarkup-rules'),
      'custom-block'  => PhabricatorEnv::getEnvConfig(
        'differential.custom-remarkup-block-rules'),
      'differential.diff' => idx($options, 'differential.diff'),
    ));
  }


  /**
   * @task engine
   */
  public static function newDiffusionMarkupEngine(array $options = array()) {
    return self::newMarkupEngine(array(
    ));
  }


  /**
   * @task engine
   */
  public static function newProfileMarkupEngine() {
    return self::newMarkupEngine(array(
    ));
  }


  /**
   * @task engine
   */
  public static function newSlowvoteMarkupEngine() {
    return self::newMarkupEngine(array(
    ));
  }


  public static function newPonderMarkupEngine(array $options = array()) {
    return self::newMarkupEngine($options);
  }


  /**
   * @task engine
   */
  private static function getMarkupEngineDefaultConfiguration() {
    return array(
      'pygments'      => PhabricatorEnv::getEnvConfig('pygments.enabled'),
      'fileproxy'     => PhabricatorEnv::getEnvConfig('files.enable-proxy'),
      'youtube'       => PhabricatorEnv::getEnvConfig(
        'remarkup.enable-embedded-youtube'),
      'custom-inline' => array(),
      'custom-block'  => array(),
      'differential.diff' => null,
      'header.generate-toc' => false,
      'macros'        => true,
      'uri.allowed-protocols' => PhabricatorEnv::getEnvConfig(
        'uri.allowed-protocols'),
      'syntax-highlighter.engine' => PhabricatorEnv::getEnvConfig(
        'syntax-highlighter.engine'),
    );
  }


  /**
   * @task engine
   */
  private static function newMarkupEngine(array $options) {

    $options += self::getMarkupEngineDefaultConfiguration();

    $engine = new PhutilRemarkupEngine();

    $engine->setConfig('preserve-linebreaks', true);
    $engine->setConfig('pygments.enabled', $options['pygments']);
    $engine->setConfig(
      'uri.allowed-protocols',
      $options['uri.allowed-protocols']);
    $engine->setConfig('differential.diff', $options['differential.diff']);
    $engine->setConfig('header.generate-toc', $options['header.generate-toc']);
    $engine->setConfig(
      'syntax-highlighter.engine',
      $options['syntax-highlighter.engine']);

    $rules = array();
    $rules[] = new PhutilRemarkupRuleEscapeRemarkup();
    $rules[] = new PhutilRemarkupRuleMonospace();

    $custom_rule_classes = $options['custom-inline'];
    if ($custom_rule_classes) {
      foreach ($custom_rule_classes as $custom_rule_class) {
        $rules[] = newv($custom_rule_class, array());
      }
    }

    $rules[] = new PhutilRemarkupRuleDocumentLink();

    if ($options['fileproxy']) {
      $rules[] = new PhabricatorRemarkupRuleProxyImage();
    }

    if ($options['youtube']) {
      $rules[] = new PhabricatorRemarkupRuleYoutube();
    }

    $rules[] = new PhutilRemarkupRuleHyperlink();
    $rules[] = new PhabricatorRemarkupRulePhriction();

    $rules[] = new PhabricatorRemarkupRuleDifferentialHandle();
    $rules[] = new PhabricatorRemarkupRuleManiphestHandle();

    $rules[] = new PhabricatorRemarkupRuleEmbedFile();

    $rules[] = new PhabricatorRemarkupRuleDifferential();
    $rules[] = new PhabricatorRemarkupRuleDiffusion();
    $rules[] = new PhabricatorRemarkupRuleManiphest();
    $rules[] = new PhabricatorRemarkupRulePaste();

    $rules[] = new PhabricatorRemarkupRuleCountdown();

    $rules[] = new PonderRuleQuestion();

    if ($options['macros']) {
      $rules[] = new PhabricatorRemarkupRuleImageMacro();
    }

    $rules[] = new PhabricatorRemarkupRuleMention();

    $rules[] = new PhutilRemarkupRuleEscapeHTML();
    $rules[] = new PhutilRemarkupRuleBold();
    $rules[] = new PhutilRemarkupRuleItalic();
    $rules[] = new PhutilRemarkupRuleDel();

    $blocks = array();
    $blocks[] = new PhutilRemarkupEngineRemarkupQuotesBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupLiteralBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupHeaderBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupListBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupCodeBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupNoteBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupTableBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupSimpleTableBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupDefaultBlockRule();

    $custom_block_rule_classes = $options['custom-block'];
    if ($custom_block_rule_classes) {
      foreach ($custom_block_rule_classes as $custom_block_rule_class) {
        $blocks[] = newv($custom_block_rule_class, array());
      }
    }

    foreach ($blocks as $block) {
      if ($block instanceof PhutilRemarkupEngineRemarkupLiteralBlockRule) {
        $literal_rules = array();
        $literal_rules[] = new PhutilRemarkupRuleEscapeHTML();
        $literal_rules[] = new PhutilRemarkupRuleLinebreaks();
        $block->setMarkupRules($literal_rules);
      } else if (
          !($block instanceof PhutilRemarkupEngineRemarkupCodeBlockRule)) {
        $block->setMarkupRules($rules);
      }
    }

    $engine->setBlockRules($blocks);

    return $engine;
  }

  public static function extractPHIDsFromMentions(array $content_blocks) {
    $mentions = array();

    $engine = self::newDifferentialMarkupEngine();

    foreach ($content_blocks as $content_block) {
      $engine->markupText($content_block);
      $phids = $engine->getTextMetadata(
        PhabricatorRemarkupRuleMention::KEY_MENTIONED,
        array());
      $mentions += $phids;
    }

    return $mentions;
  }


  /**
   * Produce a corpus summary, in a way that shortens the underlying text
   * without truncating it somewhere awkward.
   *
   * TODO: We could do a better job of this.
   *
   * @param string  Remarkup corpus to summarize.
   * @return string Summarized corpus.
   */
  public static function summarize($corpus) {

    // Major goals here are:
    //  - Don't split in the middle of a character (utf-8).
    //  - Don't split in the middle of, e.g., **bold** text, since
    //    we end up with hanging '**' in the summary.
    //  - Try not to pick an image macro, header, embedded file, etc.
    //  - Hopefully don't return too much text. We don't explicitly limit
    //    this right now.

    $blocks = preg_split("/\n *\n\s*/", trim($corpus));

    $best = null;
    foreach ($blocks as $block) {
      // This is a test for normal spaces in the block, i.e. a heuristic to
      // distinguish standard paragraphs from things like image macros. It may
      // not work well for non-latin text. We prefer to summarize with a
      // paragraph of normal words over an image macro, if possible.
      $has_space = preg_match('/\w\s\w/', $block);

      // This is a test to find embedded images and headers. We prefer to
      // summarize with a normal paragraph over a header or an embedded object,
      // if possible.
      $has_embed = preg_match('/^[{=]/', $block);

      if ($has_space && !$has_embed) {
        // This seems like a good summary, so return it.
        return $block;
      }

      if (!$best) {
        // This is the first block we found; if everything is garbage just
        // use the first block.
        $best = $block;
      }
    }

    return $best;
  }

}
