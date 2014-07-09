<?php

/**
 * An object which has one or more fields containing markup that can be
 * rendered into a display format. Commonly, the fields contain Remarkup and
 * are rendered into HTML. Implementing this interface allows you to render
 * objects through @{class:PhabricatorMarkupEngine} and benefit from caching
 * and pipelining infrastructure.
 *
 * An object may have several "fields" of markup. For example, Differential
 * revisions have a "summary" and a "test plan". In these cases, the `$field`
 * parameter is used to identify which field is being operated on. For simple
 * objects like comments, you might only have one field (say, "body"). In
 * these cases, the implementation can largely ignore the `$field` parameter.
 *
 * @task markup Markup Interface
 */
interface PhabricatorMarkupInterface {


/* -(  Markup Interface  )--------------------------------------------------- */


  /**
   * Get a key to identify this field. This should uniquely identify the block
   * of text to be rendered and be usable as a cache key. If the object has a
   * PHID, using the PHID and the field name is likely reasonable:
   *
   *   "{$phid}:{$field}"
   *
   * @param string Field name.
   * @return string Cache key up to 125 characters.
   *
   * @task markup
   */
  public function getMarkupFieldKey($field);


  /**
   * Build the engine the field should use.
   *
   * @param string Field name.
   * @return PhutilRemarkupEngine Markup engine to use.
   * @task markup
   */
  public function newMarkupEngine($field);


  /**
   * Return the contents of the specified field.
   *
   * @param string Field name.
   * @return string The raw markup contained in the field.
   * @task markup
   */
  public function getMarkupText($field);


  /**
   * Callback for final postprocessing of output. Normally, you can return
   * the output unmodified.
   *
   * @param string Field name.
   * @param string The finalized output of the engine.
   * @param string The engine which generated the output.
   * @return string Final output.
   * @task markup
   */
  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine);


  /**
   * Determine if the engine should try to use the markup cache or not.
   * Generally you should use the cache for durable/permanent content but
   * should not use the cache for temporary/draft content.
   *
   * @return bool True to use the markup cache.
   * @task markup
   */
  public function shouldUseMarkupCache($field);

}
