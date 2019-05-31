<?php

final class HeraldObjectTranscript extends Phobject {

  protected $phid;
  protected $type;
  protected $name;
  protected $fields;
  protected $appliedTransactionPHIDs;
  protected $profile;

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function getPHID() {
    return $this->phid;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setFields(array $fields) {
    foreach ($fields as $key => $value) {
      $fields[$key] = self::truncateValue($value, 4096);
    }

    $this->fields = $fields;
    return $this;
  }

  public function getFields() {
    return $this->fields;
  }

  public function setProfile(array $profile) {
    $this->profile = $profile;
    return $this;
  }

  public function getProfile() {
    return $this->profile;
  }

  public function setAppliedTransactionPHIDs($phids) {
    $this->appliedTransactionPHIDs = $phids;
    return $this;
  }

  public function getAppliedTransactionPHIDs() {
    return $this->appliedTransactionPHIDs;
  }

  private static function truncateValue($value, $length) {
    if (is_string($value)) {
      if (strlen($value) <= $length) {
        return $value;
      } else {
        // NOTE: PhutilUTF8StringTruncator has huge runtime for giant strings.
        return phutil_utf8ize(substr($value, 0, $length)."\n<...>");
      }
    } else if (is_array($value)) {
      foreach ($value as $key => $v) {
        if ($length <= 0) {
          $value['<...>'] = '<...>';
          unset($value[$key]);
        } else {
          $v = self::truncateValue($v, $length);
          $length -= strlen($v);
          $value[$key] = $v;
        }
      }
      return $value;
    } else {
      return $value;
    }
  }

}
