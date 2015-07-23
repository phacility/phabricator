<?php

final class HeraldRuleQuery extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $ruleTypes;
  private $contentTypes;
  private $disabled;
  private $triggerObjectPHIDs;

  private $needConditionsAndActions;
  private $needAppliedToPHIDs;
  private $needValidateAuthors;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function withRuleTypes(array $types) {
    $this->ruleTypes = $types;
    return $this;
  }

  public function withContentTypes(array $types) {
    $this->contentTypes = $types;
    return $this;
  }

  public function withExecutableRules($executable) {
    $this->executable = $executable;
    return $this;
  }

  public function withDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function withTriggerObjectPHIDs(array $phids) {
    $this->triggerObjectPHIDs = $phids;
    return $this;
  }

  public function needConditionsAndActions($need) {
    $this->needConditionsAndActions = $need;
    return $this;
  }

  public function needAppliedToPHIDs(array $phids) {
    $this->needAppliedToPHIDs = $phids;
    return $this;
  }

  public function needValidateAuthors($need) {
    $this->needValidateAuthors = $need;
    return $this;
  }

  protected function loadPage() {
    $table = new HeraldRule();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT rule.* FROM %T rule %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $rules) {
    $rule_ids = mpull($rules, 'getID');

    // Filter out any rules that have invalid adapters, or have adapters the
    // viewer isn't permitted to see or use (for example, Differential rules
    // if the user can't use Differential or Differential is not installed).
    $types = HeraldAdapter::getEnabledAdapterMap($this->getViewer());
    foreach ($rules as $key => $rule) {
      if (empty($types[$rule->getContentType()])) {
        $this->didRejectResult($rule);
        unset($rules[$key]);
      }
    }

    if ($this->needValidateAuthors) {
      $this->validateRuleAuthors($rules);
    }

    if ($this->needConditionsAndActions) {
      $conditions = id(new HeraldCondition())->loadAllWhere(
        'ruleID IN (%Ld)',
        $rule_ids);
      $conditions = mgroup($conditions, 'getRuleID');

      $actions = id(new HeraldAction())->loadAllWhere(
        'ruleID IN (%Ld)',
        $rule_ids);
      $actions = mgroup($actions, 'getRuleID');

      foreach ($rules as $rule) {
        $rule->attachActions(idx($actions, $rule->getID(), array()));
        $rule->attachConditions(idx($conditions, $rule->getID(), array()));
      }
    }

    if ($this->needAppliedToPHIDs) {
      $conn_r = id(new HeraldRule())->establishConnection('r');
      $applied = queryfx_all(
        $conn_r,
        'SELECT * FROM %T WHERE ruleID IN (%Ld) AND phid IN (%Ls)',
        HeraldRule::TABLE_RULE_APPLIED,
        $rule_ids,
        $this->needAppliedToPHIDs);

      $map = array();
      foreach ($applied as $row) {
        $map[$row['ruleID']][$row['phid']] = true;
      }

      foreach ($rules as $rule) {
        foreach ($this->needAppliedToPHIDs as $phid) {
          $rule->setRuleApplied(
            $phid,
            isset($map[$rule->getID()][$phid]));
        }
      }
    }

    $object_phids = array();
    foreach ($rules as $rule) {
      if ($rule->isObjectRule()) {
        $object_phids[] = $rule->getTriggerObjectPHID();
      }
    }

    if ($object_phids) {
      $objects = id(new PhabricatorObjectQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs($object_phids)
        ->execute();
      $objects = mpull($objects, null, 'getPHID');
    } else {
      $objects = array();
    }

    foreach ($rules as $key => $rule) {
      if ($rule->isObjectRule()) {
        $object = idx($objects, $rule->getTriggerObjectPHID());
        if (!$object) {
          unset($rules[$key]);
          continue;
        }
        $rule->attachTriggerObject($object);
      }
    }

    return $rules;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'rule.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'rule.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'rule.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->ruleTypes) {
      $where[] = qsprintf(
        $conn_r,
        'rule.ruleType IN (%Ls)',
        $this->ruleTypes);
    }

    if ($this->contentTypes) {
      $where[] = qsprintf(
        $conn_r,
        'rule.contentType IN (%Ls)',
        $this->contentTypes);
    }

    if ($this->disabled !== null) {
      $where[] = qsprintf(
        $conn_r,
        'rule.isDisabled = %d',
        (int)$this->disabled);
    }

    if ($this->triggerObjectPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'rule.triggerObjectPHID IN (%Ls)',
        $this->triggerObjectPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  private function validateRuleAuthors(array $rules) {
    // "Global" and "Object" rules always have valid authors.
    foreach ($rules as $key => $rule) {
      if ($rule->isGlobalRule() || $rule->isObjectRule()) {
        $rule->attachValidAuthor(true);
        unset($rules[$key]);
        continue;
      }
    }

    if (!$rules) {
      return;
    }

    // For personal rules, the author needs to exist and not be disabled.
    $user_phids = mpull($rules, 'getAuthorPHID');
    $users = id(new PhabricatorPeopleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($user_phids)
      ->execute();
    $users = mpull($users, null, 'getPHID');

    foreach ($rules as $key => $rule) {
      $author_phid = $rule->getAuthorPHID();
      if (empty($users[$author_phid])) {
        $rule->attachValidAuthor(false);
        continue;
      }
      if (!$users[$author_phid]->isUserActivated()) {
        $rule->attachValidAuthor(false);
        continue;
      }

      $rule->attachValidAuthor(true);
      $rule->attachAuthor($users[$author_phid]);
    }
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

}
