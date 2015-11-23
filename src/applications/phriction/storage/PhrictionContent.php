<?php

/**
 * @task markup Markup Interface
 */
final class PhrictionContent extends PhrictionDAO
  implements PhabricatorMarkupInterface {

  const MARKUP_FIELD_BODY = 'markup:body';

  protected $id;
  protected $documentID;
  protected $version;
  protected $authorPHID;

  protected $title;
  protected $slug;
  protected $content;
  protected $description;

  protected $changeType;
  protected $changeRef;

  private $renderedTableOfContents;

  public function renderContent(PhabricatorUser $viewer) {
    return PhabricatorMarkupEngine::renderOneObject(
      $this,
      self::MARKUP_FIELD_BODY,
      $viewer);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'version' => 'uint32',
        'title' => 'sort',
        'slug' => 'text128',
        'content' => 'text',
        'changeType' => 'uint32',
        'changeRef' => 'uint32?',

        // T6203/NULLABILITY
        // This should just be empty if not provided?
        'description' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'documentID' => array(
          'columns' => array('documentID', 'version'),
          'unique' => true,
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID'),
        ),
        'slug' => array(
          'columns' => array('slug'),
        ),
      ),
    ) + parent::getConfiguration();
  }


/* -(  Markup Interface  )--------------------------------------------------- */


  /**
   * @task markup
   */
  public function getMarkupFieldKey($field) {
    if ($this->shouldUseMarkupCache($field)) {
      $id = $this->getID();
    } else {
      $id = PhabricatorHash::digest($this->getMarkupText($field));
    }
    return "phriction:{$field}:{$id}";
  }


  /**
   * @task markup
   */
  public function getMarkupText($field) {
    return $this->getContent();
  }


  /**
   * @task markup
   */
  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newPhrictionMarkupEngine();
  }


  /**
   * @task markup
   */
  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {

    $this->renderedTableOfContents =
      PhutilRemarkupHeaderBlockRule::renderTableOfContents($engine);

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $output);
  }

  /**
   * @task markup
   */
  public function getRenderedTableOfContents() {
    return $this->renderedTableOfContents;
  }


  /**
   * @task markup
   */
  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }


}
