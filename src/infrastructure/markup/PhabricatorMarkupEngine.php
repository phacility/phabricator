<?php

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
final class PhabricatorMarkupEngine extends Phobject {

  private $objects = array();
  private $viewer;
  private $contextObject;
  private $version = 15;
  private $engineCaches = array();


/* -(  Markup Pipeline  )---------------------------------------------------- */


  /**
   * Convenience method for pushing a single object through the markup
   * pipeline.
   *
   * @param PhabricatorMarkupInterface  The object to render.
   * @param string                      The field to render.
   * @param PhabricatorUser             User viewing the markup.
   * @param object                      A context object for policy checks
   * @return string                     Marked up output.
   * @task markup
   */
  public static function renderOneObject(
    PhabricatorMarkupInterface $object,
    $field,
    PhabricatorUser $viewer,
    $context_object = null) {
    return id(new PhabricatorMarkupEngine())
      ->setViewer($viewer)
      ->setContextObject($context_object)
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
      $engines[$key]->setConfig('contextObject', $this->contextObject);
    }

    // Load or build the preprocessor caches.
    $blocks = $this->loadPreprocessorCaches($engines, $objects);
    $blocks = mpull($blocks, 'getCacheData');

    $this->engineCaches = $blocks;

    // Finalize the output.
    foreach ($objects as $key => $info) {
      $engine = $engines[$key];
      $field = $info['field'];
      $object = $info['object'];

      $output = $engine->postprocessText($blocks[$key]);
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
    $this->requireKeyProcessed($key);

    return $this->objects[$key]['output'];
  }


  /**
   * Retrieve engine metadata for a given field.
   *
   * @param PhabricatorMarkupInterface  The object to retrieve.
   * @param string                      The field to retrieve.
   * @param string                      The engine metadata field to retrieve.
   * @param wild                        Optional default value.
   * @task markup
   */
  public function getEngineMetadata(
    PhabricatorMarkupInterface $object,
    $field,
    $metadata_key,
    $default = null) {

    $key = $this->getMarkupFieldKey($object, $field);
    $this->requireKeyProcessed($key);

    return idx($this->engineCaches[$key]['metadata'], $metadata_key, $default);
  }


  /**
   * @task markup
   */
  private function requireKeyProcessed($key) {
    if (empty($this->objects[$key])) {
      throw new Exception(
        pht(
          "Call %s before using results (key = '%s').",
          'addObject()',
          $key));
    }

    if (!isset($this->objects[$key]['output'])) {
      throw new Exception(
        pht(
          'Call %s before using results.',
          'process()'));
    }
  }


  /**
   * @task markup
   */
  private function getMarkupFieldKey(
    PhabricatorMarkupInterface $object,
    $field) {

    static $custom;
    if ($custom === null) {
      $custom = array_merge(
        self::loadCustomInlineRules(),
        self::loadCustomBlockRules());

      $custom = mpull($custom, 'getRuleVersion', null);
      ksort($custom);
      $custom = PhabricatorHash::digestForIndex(serialize($custom));
    }

    return $object->getMarkupFieldKey($field).'@'.$this->version.'@'.$custom;
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
      try {
        $blocks = id(new PhabricatorMarkupCache())->loadAllWhere(
          'cacheKey IN (%Ls)',
          array_keys($use_cache));
        $blocks = mpull($blocks, null, 'getCacheKey');
      } catch (Exception $ex) {
        phlog($ex);
      }
    }

    foreach ($objects as $key => $info) {
      // False check in case MySQL doesn't support unicode characters
      // in the string (T1191), resulting in unserialize returning false.
      if (isset($blocks[$key]) && $blocks[$key]->getCacheData() !== false) {
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
          $blocks[$key]->replace();
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

  /**
   * Set the context object. Used to implement object permissions.
   *
   * @param The object in which context this remarkup is used.
   * @return this
   * @task markup
   */
  public function setContextObject($object) {
    $this->contextObject = $object;
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
      'uri.full' => true,
    ));
  }


  /**
   * @task engine
   */
  public static function newFeedMarkupEngine() {
    return self::newMarkupEngine(
      array(
        'macros'      => false,
        'youtube'     => false,
      ));
  }

  /**
   * @task engine
   */
  public static function newCalendarMarkupEngine() {
    return self::newMarkupEngine(array(
    ));
  }


  /**
   * @task engine
   */
  public static function newDifferentialMarkupEngine(array $options = array()) {
    return self::newMarkupEngine(array(
      'differential.diff' => idx($options, 'differential.diff'),
    ));
  }


  /**
   * @task engine
   */
  public static function newDiffusionMarkupEngine(array $options = array()) {
    return self::newMarkupEngine(array(
      'header.generate-toc' => true,
    ));
  }

  /**
   * @task engine
   */
  public static function getEngine($ruleset = 'default') {
    static $engines = array();
    if (isset($engines[$ruleset])) {
      return $engines[$ruleset];
    }

    $engine = null;
    switch ($ruleset) {
      case 'default':
        $engine = self::newMarkupEngine(array());
        break;
      case 'nolinebreaks':
        $engine = self::newMarkupEngine(array());
        $engine->setConfig('preserve-linebreaks', false);
        break;
      case 'diffusion-readme':
        $engine = self::newMarkupEngine(array());
        $engine->setConfig('preserve-linebreaks', false);
        $engine->setConfig('header.generate-toc', true);
        break;
      case 'diviner':
        $engine = self::newMarkupEngine(array());
        $engine->setConfig('preserve-linebreaks', false);
  //    $engine->setConfig('diviner.renderer', new DivinerDefaultRenderer());
        $engine->setConfig('header.generate-toc', true);
        break;
      case 'extract':
        // Engine used for reference/edge extraction. Turn off anything which
        // is slow and doesn't change reference extraction.
        $engine = self::newMarkupEngine(array());
        $engine->setConfig('pygments.enabled', false);
        break;
      default:
        throw new Exception(pht('Unknown engine ruleset: %s!', $ruleset));
    }

    $engines[$ruleset] = $engine;
    return $engine;
  }

  /**
   * @task engine
   */
  private static function getMarkupEngineDefaultConfiguration() {
    return array(
      'pygments'      => PhabricatorEnv::getEnvConfig('pygments.enabled'),
      'youtube'       => PhabricatorEnv::getEnvConfig(
        'remarkup.enable-embedded-youtube'),
      'differential.diff' => null,
      'header.generate-toc' => false,
      'macros'        => true,
      'uri.allowed-protocols' => PhabricatorEnv::getEnvConfig(
        'uri.allowed-protocols'),
      'uri.full' => false,
      'syntax-highlighter.engine' => PhabricatorEnv::getEnvConfig(
        'syntax-highlighter.engine'),
      'preserve-linebreaks' => true,
    );
  }


  /**
   * @task engine
   */
  public static function newMarkupEngine(array $options) {
    $options += self::getMarkupEngineDefaultConfiguration();

    $engine = new PhutilRemarkupEngine();

    $engine->setConfig('preserve-linebreaks', $options['preserve-linebreaks']);
    $engine->setConfig('pygments.enabled', $options['pygments']);
    $engine->setConfig(
      'uri.allowed-protocols',
      $options['uri.allowed-protocols']);
    $engine->setConfig('differential.diff', $options['differential.diff']);
    $engine->setConfig('header.generate-toc', $options['header.generate-toc']);
    $engine->setConfig(
      'syntax-highlighter.engine',
      $options['syntax-highlighter.engine']);

    $engine->setConfig('uri.full', $options['uri.full']);

    $rules = array();
    $rules[] = new PhutilRemarkupEscapeRemarkupRule();
    $rules[] = new PhutilRemarkupMonospaceRule();


    $rules[] = new PhutilRemarkupDocumentLinkRule();
    $rules[] = new PhabricatorNavigationRemarkupRule();

    if ($options['youtube']) {
      $rules[] = new PhabricatorYoutubeRemarkupRule();
    }

    $applications = PhabricatorApplication::getAllInstalledApplications();
    foreach ($applications as $application) {
      foreach ($application->getRemarkupRules() as $rule) {
        $rules[] = $rule;
      }
    }

    $rules[] = new PhutilRemarkupHyperlinkRule();

    if ($options['macros']) {
      $rules[] = new PhabricatorImageMacroRemarkupRule();
      $rules[] = new PhabricatorMemeRemarkupRule();
    }

    $rules[] = new PhutilRemarkupBoldRule();
    $rules[] = new PhutilRemarkupItalicRule();
    $rules[] = new PhutilRemarkupDelRule();
    $rules[] = new PhutilRemarkupUnderlineRule();

    foreach (self::loadCustomInlineRules() as $rule) {
      $rules[] = $rule;
    }

    $blocks = array();
    $blocks[] = new PhutilRemarkupQuotesBlockRule();
    $blocks[] = new PhutilRemarkupReplyBlockRule();
    $blocks[] = new PhutilRemarkupLiteralBlockRule();
    $blocks[] = new PhutilRemarkupHeaderBlockRule();
    $blocks[] = new PhutilRemarkupHorizontalRuleBlockRule();
    $blocks[] = new PhutilRemarkupListBlockRule();
    $blocks[] = new PhutilRemarkupCodeBlockRule();
    $blocks[] = new PhutilRemarkupNoteBlockRule();
    $blocks[] = new PhutilRemarkupTableBlockRule();
    $blocks[] = new PhutilRemarkupSimpleTableBlockRule();
    $blocks[] = new PhutilRemarkupInterpreterBlockRule();
    $blocks[] = new PhutilRemarkupDefaultBlockRule();

    foreach (self::loadCustomBlockRules() as $rule) {
      $blocks[] = $rule;
    }

    foreach ($blocks as $block) {
      $block->setMarkupRules($rules);
    }

    $engine->setBlockRules($blocks);

    return $engine;
  }

  public static function extractPHIDsFromMentions(
    PhabricatorUser $viewer,
    array $content_blocks) {

    $mentions = array();

    $engine = self::newDifferentialMarkupEngine();
    $engine->setConfig('viewer', $viewer);

    foreach ($content_blocks as $content_block) {
      $engine->markupText($content_block);
      $phids = $engine->getTextMetadata(
        PhabricatorMentionRemarkupRule::KEY_MENTIONED,
        array());
      $mentions += $phids;
    }

    return $mentions;
  }

  public static function extractFilePHIDsFromEmbeddedFiles(
    PhabricatorUser $viewer,
    array $content_blocks) {
    $files = array();

    $engine = self::newDifferentialMarkupEngine();
    $engine->setConfig('viewer', $viewer);

    foreach ($content_blocks as $content_block) {
      $engine->markupText($content_block);
      $phids = $engine->getTextMetadata(
        PhabricatorEmbedFileRemarkupRule::KEY_EMBED_FILE_PHIDS,
        array());
      foreach ($phids as $phid) {
        $files[$phid] = $phid;
      }
    }

    return array_values($files);
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

    $blocks = preg_split("/\n *\n\s*/", $corpus);

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

  private static function loadCustomInlineRules() {
    return id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorRemarkupCustomInlineRule')
      ->loadObjects();
  }

  private static function loadCustomBlockRules() {
    return id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorRemarkupCustomBlockRule')
      ->loadObjects();
  }

}
