<?php

final class HeraldPreCommitRefAdapter extends HeraldPreCommitAdapter {

  const FIELD_REF_TYPE = 'ref-type';
  const FIELD_REF_NAME = 'ref-name';
  const FIELD_REF_CHANGE = 'ref-change';

  const VALUE_REF_TYPE = 'value-ref-type';
  const VALUE_REF_CHANGE = 'value-ref-change';

  public function getAdapterContentName() {
    return pht('Commit Hook: Branches/Tags/Bookmarks');
  }

  public function getAdapterSortOrder() {
    return 2000;
  }

  public function getAdapterContentDescription() {
    return pht(
      "React to branches and tags being pushed to hosted repositories.\n".
      "Hook rules can block changes and send push summary mail.");
  }

  public function getFieldNameMap() {
    return array(
      self::FIELD_REF_TYPE => pht('Ref type'),
      self::FIELD_REF_NAME => pht('Ref name'),
      self::FIELD_REF_CHANGE => pht('Ref change type'),
    ) + parent::getFieldNameMap();
  }

  public function getFields() {
    return array_merge(
      array(
        self::FIELD_REF_TYPE,
        self::FIELD_REF_NAME,
        self::FIELD_REF_CHANGE,
        self::FIELD_REPOSITORY,
        self::FIELD_REPOSITORY_PROJECTS,
        self::FIELD_PUSHER,
        self::FIELD_PUSHER_PROJECTS,
      ),
      parent::getFields());
  }

  public function getConditionsForField($field) {
    switch ($field) {
      case self::FIELD_REF_NAME:
        return array(
          self::CONDITION_IS,
          self::CONDITION_IS_NOT,
          self::CONDITION_CONTAINS,
          self::CONDITION_REGEXP,
        );
      case self::FIELD_REF_TYPE:
        return array(
          self::CONDITION_IS,
          self::CONDITION_IS_NOT,
        );
      case self::FIELD_REF_CHANGE:
        return array(
          self::CONDITION_HAS_BIT,
          self::CONDITION_NOT_BIT,
        );
    }
    return parent::getConditionsForField($field);
  }

  public function getValueTypeForFieldAndCondition($field, $condition) {
    switch ($field) {
      case self::FIELD_REF_TYPE:
        return self::VALUE_REF_TYPE;
      case self::FIELD_REF_CHANGE:
        return self::VALUE_REF_CHANGE;
    }

    return parent::getValueTypeForFieldAndCondition($field, $condition);
  }

  public function getHeraldName() {
    return pht('Push Log (Ref)');
  }

  public function getHeraldField($field) {
    $log = $this->getObject();
    switch ($field) {
      case self::FIELD_REF_TYPE:
        return $log->getRefType();
      case self::FIELD_REF_NAME:
        return $log->getRefName();
      case self::FIELD_REF_CHANGE:
        return $log->getChangeFlags();
      case self::FIELD_REPOSITORY:
        return $this->getHookEngine()->getRepository()->getPHID();
      case self::FIELD_REPOSITORY_PROJECTS:
        return $this->getHookEngine()->getRepository()->getProjectPHIDs();
      case self::FIELD_PUSHER:
        return $this->getHookEngine()->getViewer()->getPHID();
      case self::FIELD_PUSHER_PROJECTS:
        return $this->getHookEngine()->loadViewerProjectPHIDsForHerald();
    }

    return parent::getHeraldField($field);
  }

}
