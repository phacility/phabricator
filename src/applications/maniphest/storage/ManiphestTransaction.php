<?php

/**
 * @task markup Markup Interface
 * @group maniphest
 */
final class ManiphestTransaction extends ManiphestDAO
  implements PhabricatorMarkupInterface {

  const MARKUP_FIELD_BODY = 'markup:body';

  protected $taskID;
  protected $authorPHID;
  protected $transactionType;
  protected $oldValue;
  protected $newValue;
  protected $comments;
  protected $metadata = array();
  protected $contentSource;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'oldValue' => self::SERIALIZATION_JSON,
        'newValue' => self::SERIALIZATION_JSON,
        'metadata' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function extractPHIDs() {
    $phids = array();

    switch ($this->getTransactionType()) {
      case ManiphestTransactionType::TYPE_CCS:
      case ManiphestTransactionType::TYPE_PROJECTS:
        foreach ($this->getOldValue() as $phid) {
          $phids[] = $phid;
        }
        foreach ($this->getNewValue() as $phid) {
          $phids[] = $phid;
        }
        break;
      case ManiphestTransactionType::TYPE_OWNER:
        $phids[] = $this->getOldValue();
        $phids[] = $this->getNewValue();
        break;
      case ManiphestTransactionType::TYPE_EDGE:
        $phids = array_merge(
          $phids,
          array_keys($this->getOldValue() + $this->getNewValue()));
        break;
      case ManiphestTransactionType::TYPE_ATTACH:
        $old = $this->getOldValue();
        $new = $this->getNewValue();
        if (!is_array($old)) {
          $old = array();
        }
        if (!is_array($new)) {
          $new = array();
        }
        $val = array_merge(array_values($old), array_values($new));
        foreach ($val as $stuff) {
          foreach ($stuff as $phid => $ignored) {
            $phids[] = $phid;
          }
        }
        break;
    }

    $phids[] = $this->getAuthorPHID();

    return $phids;
  }

  public function getMetadataValue($key, $default = null) {
    if (!is_array($this->metadata)) {
      return $default;
    }
    return idx($this->metadata, $key, $default);
  }

  public function setMetadataValue($key, $value) {
    if (!is_array($this->metadata)) {
      $this->metadata = array();
    }
    $this->metadata[$key] = $value;
    return $this;
  }

  public function canGroupWith($target) {
    if ($target->getAuthorPHID() != $this->getAuthorPHID()) {
      return false;
    }
    if ($target->hasComments() && $this->hasComments()) {
      return false;
    }
    $ttime = $target->getDateCreated();
    $stime = $this->getDateCreated();
    if (abs($stime - $ttime) > 60) {
      return false;
    }

    if ($target->getTransactionType() == $this->getTransactionType()) {
      $aux_type = ManiphestTransactionType::TYPE_AUXILIARY;
      if ($this->getTransactionType() == $aux_type) {
        $that_key = $target->getMetadataValue('aux:key');
        $this_key = $this->getMetadataValue('aux:key');
        if ($that_key == $this_key) {
          return false;
        }
      } else {
        return false;
      }
    }

    return true;
  }

  public function hasComments() {
    return (bool)strlen(trim($this->getComments()));
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source->serialize();
    return $this;
  }

  public function getContentSource() {
    return PhabricatorContentSource::newFromSerialized($this->contentSource);
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
    return "maniphest:x:{$field}:{$id}";
  }


  /**
   * @task markup
   */
  public function getMarkupText($field) {
    return $this->getComments();
  }


  /**
   * @task markup
   */
  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newManiphestMarkupEngine();
  }


  /**
   * @task markup
   */
  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }


  /**
   * @task markup
   */
  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }

}
