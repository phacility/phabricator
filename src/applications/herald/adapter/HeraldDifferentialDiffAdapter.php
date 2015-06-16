<?php

final class HeraldDifferentialDiffAdapter extends HeraldDifferentialAdapter {

  public function getAdapterApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  protected function loadChangesets() {
    return $this->loadChangesetsWithHunks();
  }

  protected function loadChangesetsWithHunks() {
    return $this->getDiff()->getChangesets();
  }

  public function getObject() {
    return $this->getDiff();
  }

  public function getAdapterContentType() {
    return 'differential.diff';
  }

  public function getAdapterContentName() {
    return pht('Differential Diffs');
  }

  public function getAdapterContentDescription() {
    return pht(
      "React to new diffs being uploaded, before writes occur.\n".
      "These rules can reject diffs before they are written to permanent ".
      "storage, to prevent users from accidentally uploading private keys or ".
      "other sensitive information.");
  }

  public function supportsRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return true;
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
      default:
        return false;
    }
  }

  public function getFields() {
    return array_merge(
      array(
        self::FIELD_AUTHOR,
        self::FIELD_AUTHOR_PROJECTS,
        self::FIELD_REPOSITORY,
        self::FIELD_REPOSITORY_PROJECTS,
        self::FIELD_DIFF_FILE,
        self::FIELD_DIFF_CONTENT,
        self::FIELD_DIFF_ADDED_CONTENT,
        self::FIELD_DIFF_REMOVED_CONTENT,
      ),
      parent::getFields());
  }

  public function getRepetitionOptions() {
    return array(
      HeraldRepetitionPolicyConfig::FIRST,
    );
  }

  public function getPHID() {
    return $this->getObject()->getPHID();
  }

  public function getHeraldName() {
    return pht('New Diff');
  }

  public function getActionNameMap($rule_type) {
    return array(
      self::ACTION_BLOCK => pht('Block diff with message'),
    ) + parent::getActionNameMap($rule_type);
  }

  public function getHeraldField($field) {
    switch ($field) {
      case self::FIELD_AUTHOR:
        return $this->getObject()->getAuthorPHID();
        break;
      case self::FIELD_AUTHOR_PROJECTS:
        $author_phid = $this->getHeraldField(self::FIELD_AUTHOR);
        if (!$author_phid) {
          return array();
        }

        $projects = id(new PhabricatorProjectQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withMemberPHIDs(array($author_phid))
          ->execute();

        return mpull($projects, 'getPHID');
      case self::FIELD_DIFF_FILE:
        return $this->loadAffectedPaths();
      case self::FIELD_REPOSITORY:
        $repository = $this->loadRepository();
        if (!$repository) {
          return null;
        }
        return $repository->getPHID();
      case self::FIELD_REPOSITORY_PROJECTS:
        $repository = $this->loadRepository();
        if (!$repository) {
          return array();
        }
        return $repository->getProjectPHIDs();
      case self::FIELD_DIFF_CONTENT:
        return $this->loadContentDictionary();
      case self::FIELD_DIFF_ADDED_CONTENT:
        return $this->loadAddedContentDictionary();
      case self::FIELD_DIFF_REMOVED_CONTENT:
        return $this->loadRemovedContentDictionary();
    }

    return parent::getHeraldField($field);
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array_merge(
          array(
            self::ACTION_BLOCK,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
    }
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');

    $result = array();
    foreach ($effects as $effect) {
      $action = $effect->getAction();
      switch ($action) {
        case self::ACTION_BLOCK:
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Blocked diff.'));
          break;
        default:
          $result[] = $this->applyStandardEffect($effect);
          break;
      }
    }

    return $result;
  }

}
