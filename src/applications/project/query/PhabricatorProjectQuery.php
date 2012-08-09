<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorProjectQuery extends PhabricatorCursorPagedPolicyQuery {

  private $ids;
  private $phids;
  private $memberPHIDs;

  private $status       = 'status-any';
  const STATUS_ANY      = 'status-any';
  const STATUS_OPEN     = 'status-open';
  const STATUS_CLOSED   = 'status-closed';
  const STATUS_ACTIVE   = 'status-active';
  const STATUS_ARCHIVED = 'status-archived';

  private $needMembers;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withMemberPHIDs(array $member_phids) {
    $this->memberPHIDs = $member_phids;
    return $this;
  }

  public function needMembers($need_members) {
    $this->needMembers = $need_members;
    return $this;
  }

  protected function getPagingColumn() {
    return 'name';
  }

  protected function getPagingValue($result) {
    return $result->getName();
  }

  public function loadPage() {
    $table = new PhabricatorProject();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT p.* FROM %T p %Q %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildGroupClause($conn_r),
      'ORDER BY name',
      $this->buildLimitClause($conn_r));

    $projects = $table->loadAllFromArray($data);

    if ($projects && $this->needMembers) {
      $etype = PhabricatorEdgeConfig::TYPE_PROJ_MEMBER;
      $members = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($projects, 'getPHID'))
        ->withEdgeTypes(array($etype))
        ->execute();
      foreach ($projects as $project) {
        $phid = $project->getPHID();
        $project->attachMemberPHIDs(array_keys($members[$phid][$etype]));
      }
    }

    return $projects;
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->status != self::STATUS_ANY) {
      switch ($this->status) {
        case self::STATUS_OPEN:
        case self::STATUS_ACTIVE:
          $filter = array(
            PhabricatorProjectStatus::STATUS_ACTIVE,
          );
          break;
        case self::STATUS_CLOSED:
        case self::STATUS_ARCHIVED:
          $filter = array(
            PhabricatorProjectStatus::STATUS_ARCHIVED,
          );
          break;
        default:
          throw new Exception(
            "Unknown project status '{$this->status}'!");
      }
      $where[] = qsprintf(
        $conn_r,
        'status IN (%Ld)',
        $filter);
    }

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->memberPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'e.type = %s AND e.dst IN (%Ls)',
        PhabricatorEdgeConfig::TYPE_PROJ_MEMBER,
        $this->memberPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  private function buildGroupClause($conn_r) {
    if ($this->memberPHIDs) {
      return 'GROUP BY p.id';
    } else {
      return '';
    }
  }

  private function buildJoinClause($conn_r) {
    $joins = array();

    if ($this->memberPHIDs) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T e ON e.src = p.phid',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE);
    }

    return implode(' ', $joins);
  }

}
