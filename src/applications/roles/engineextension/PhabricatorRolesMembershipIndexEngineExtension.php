<?php

final class PhabricatorRolesMembershipIndexEngineExtension
  extends PhabricatorIndexEngineExtension {

  const EXTENSIONKEY = 'role.members';

  public function getExtensionName() {
    return pht('Role Members');
  }

  public function shouldIndexObject($object) {
    if (!($object instanceof PhabricatorRole)) {
      return false;
    }

    return true;
  }

  public function indexObject(
    PhabricatorIndexEngine $engine,
    $object) {

    $this->rematerialize($object);
  }

  public function rematerialize(PhabricatorRole $role) {
    $materialize = $role->getAncestorRoles();
    array_unshift($materialize, $role);

    foreach ($materialize as $role) {
      $this->materializeRole($role);
    }
  }

  private function materializeRole(PhabricatorRole $role) {
    $material_type = PhabricatorRoleMaterializedMemberEdgeType::EDGECONST;
    $member_type = PhabricatorRoleRoleHasMemberEdgeType::EDGECONST;

    $role_phid = $role->getPHID();

    if ($role->isMilestone()) {
      $source_phids = array($role->getParentRolePHID());
      $has_subroles = false;
    } else {
      $descendants = id(new PhabricatorRoleQuery())
        ->setViewer($this->getViewer())
        ->withAncestorRolePHIDs(array($role->getPHID()))
        ->withIsMilestone(false)
        ->withHasSubroles(false)
        ->execute();
      $descendant_phids = mpull($descendants, 'getPHID');

      if ($descendant_phids) {
        $source_phids = $descendant_phids;
        $has_subroles = true;
      } else {
        $source_phids = array($role->getPHID());
        $has_subroles = false;
      }
    }

    $conn_w = $role->establishConnection('w');

    $any_milestone = queryfx_one(
      $conn_w,
      'SELECT id FROM %T
        WHERE parentRolePHID = %s AND milestoneNumber IS NOT NULL
        LIMIT 1',
      $role->getTableName(),
      $role_phid);
    $has_milestones = (bool)$any_milestone;

    $role->openTransaction();

      // Copy current member edges to create new materialized edges.

      // See T13596. Avoid executing this as an "INSERT ... SELECT" to reduce
      // the required level of table locking. Since we're decomposing it into
      // "SELECT" + "INSERT" anyway, we can also compute exactly which rows
      // need to be modified.

      $have_rows = queryfx_all(
        $conn_w,
        'SELECT dst FROM %T
          WHERE src = %s AND type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        $role_phid,
        $material_type);

      $want_rows = queryfx_all(
        $conn_w,
        'SELECT dst, dateCreated, seq FROM %T
          WHERE src IN (%Ls) AND type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        $source_phids,
        $member_type);

      $have_phids = ipull($have_rows, 'dst', 'dst');
      $want_phids = ipull($want_rows, null, 'dst');

      $rem_phids = array_diff_key($have_phids, $want_phids);
      $rem_phids = array_keys($rem_phids);

      $add_phids = array_diff_key($want_phids, $have_phids);
      $add_phids = array_keys($add_phids);

      $rem_sql = array();
      foreach ($rem_phids as $rem_phid) {
        $rem_sql[] = qsprintf(
          $conn_w,
          '%s',
          $rem_phid);
      }

      $add_sql = array();
      foreach ($add_phids as $add_phid) {
        $add_row = $want_phids[$add_phid];
        $add_sql[] = qsprintf(
          $conn_w,
          '(%s, %d, %s, %d, %d)',
          $role_phid,
          $material_type,
          $add_row['dst'],
          $add_row['dateCreated'],
          $add_row['seq']);
      }

      // Remove materialized members who are no longer role members.

      if ($rem_sql) {
        foreach (PhabricatorLiskDAO::chunkSQL($rem_sql) as $sql_chunk) {
          queryfx(
            $conn_w,
            'DELETE FROM %T
              WHERE src = %s AND type = %s AND dst IN (%LQ)',
            PhabricatorEdgeConfig::TABLE_NAME_EDGE,
            $role_phid,
            $material_type,
            $sql_chunk);
        }
      }

      // Add role members who are not yet materialized members.

      if ($add_sql) {
        foreach (PhabricatorLiskDAO::chunkSQL($add_sql) as $sql_chunk) {
          queryfx(
            $conn_w,
            'INSERT IGNORE INTO %T (src, type, dst, dateCreated, seq)
              VALUES %LQ',
            PhabricatorEdgeConfig::TABLE_NAME_EDGE,
            $sql_chunk);
        }
      }

      // Update the hasSubroles flag.
      queryfx(
        $conn_w,
        'UPDATE %T SET hasSubroles = %d WHERE id = %d',
        $role->getTableName(),
        (int)$has_subroles,
        $role->getID());

      // Update the hasMilestones flag.
      queryfx(
        $conn_w,
        'UPDATE %T SET hasMilestones = %d WHERE id = %d',
        $role->getTableName(),
        (int)$has_milestones,
        $role->getID());

    $role->saveTransaction();
  }

}
