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

  public function renderContent(PhabricatorUser $viewer) {
    return PhabricatorMarkupEngine::renderOneObject(
      $this,
      self::MARKUP_FIELD_BODY,
      $viewer);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'version' => 'uint32',
        'title' => 'text',
        'slug' => 'text128',
        'content' => 'text',
        'description' => 'text',
        'changeType' => 'uint32',
        'changeRef' => 'uint32?',
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
          'columns' => array('slug(255)'),
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

    $toc = PhutilRemarkupHeaderBlockRule::renderTableOfContents(
      $engine);

    if ($toc) {
      $toc = phutil_tag_div('phabricator-remarkup-toc', array(
        phutil_tag_div(
          'phabricator-remarkup-toc-header',
          pht('Table of Contents')),
        $toc,
      ));
    }

    return phutil_tag_div('phabricator-remarkup', array($toc, $output));
  }


  /**
   * @task markup
   */
  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }


}
