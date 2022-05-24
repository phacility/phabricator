<?php

final class HeraldRuleQuery extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $ruleTypes;
  private $contentTypes;
  private $disabled;
  private $active;
  private $datasourceQuery;
  private $triggerObjectPHIDs;
  private $affectedObjectPHIDs;

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

  public function withDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function withActive($active) {
    $this->active = $active;
    return $this;
  }

  public function withDatasourceQuery($query) {
    $this->datasourceQuery = $query;
    return $this;
  }

  public function withTriggerObjectPHIDs(array $phids) {
    $this->triggerObjectPHIDs = $phids;
    return $this;
  }

  public function withAffectedObjectPHIDs(array $phids) {
    $this->affectedObjectPHIDs = $phids;
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

  public function newResultObject() {
    return new HeraldRule();
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

    if ($this->needValidateAuthors || ($this->active !== null)) {
      $this->validateRuleAuthors($rules);
    }

    if ($this->active !== null) {
      $need_active = (bool)$this->active;
      foreach ($rules as $key => $rule) {
        if ($rule->getIsDisabled()) {
          $is_active = false;
        } else if (!$rule->hasValidAuthor()) {
          $is_active = false;
        } else {
          $is_active = true;
        }

        if ($is_active != $need_active) {
          unset($rules[$key]);
        }
      }
    }

    if (!$rules) {
      return array();
    }

    if ($this->needConditionsAndActions) {
      $conditions = id(new HeraldCondition())->loadAllWhere(
        'ruleID IN (%Ld)',
        $rule_ids);
      $conditions = mgroup($conditions, 'getRuleID');

      $actions = id(new HeraldActionRecord())->loadAllWhere(
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

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'rule.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'rule.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'rule.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->ruleTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'rule.ruleType IN (%Ls)',
        $this->ruleTypes);
    }

    if ($this->contentTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'rule.contentType IN (%Ls)',
        $this->contentTypes);
    }

    if ($this->disabled !== null) {
      $where[] = qsprintf(
        $conn,
        'rule.isDisabled = %d',
        (int)$this->disabled);
    }

    if ($this->active !== null) {
      $where[] = qsprintf(
        $conn,
        'rule.isDisabled = %d',
        (int)(!$this->active));
    }

    if ($this->datasourceQuery !== null) {
      $where[] = qsprintf(
        $conn,
        'rule.name LIKE %>',
        $this->datasourceQuery);
    }

    if ($this->triggerObjectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'rule.triggerObjectPHID IN (%Ls)',
        $this->triggerObjectPHIDs);
    }

    if ($this->affectedObjectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'edge_affects.dst IN (%Ls)',
        $this->affectedObjectPHIDs);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->affectedObjectPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T edge_affects ON rule.phid = edge_affects.src
          AND edge_affects.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        HeraldRuleActionAffectsObjectEdgeType::EDGECONST);
    }

    return $joins;
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

  protected function getPrimaryTableAlias() {
    return 'rule';
  }

}
