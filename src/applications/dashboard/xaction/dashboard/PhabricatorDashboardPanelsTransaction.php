<?php

final class PhabricatorDashboardPanelsTransaction
  extends PhabricatorDashboardTransactionType {

  const TRANSACTIONTYPE = 'panels';

  public function generateOldValue($object) {
    return $object->getRawPanels();
  }

  public function applyInternalEffects($object, $value) {
    $object->setRawPanels($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the panels on this dashboard.',
      $this->renderAuthor());
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();
    $errors = array();

    $ref_list = $object->getPanelRefList();
    $columns = $ref_list->getColumns();

    $old_phids = $object->getPanelPHIDs();
    $old_phids = array_fuse($old_phids);

    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      if (!is_array($new_value)) {
        $errors[] = $this->newInvalidError(
          pht('Panels must be a list of panel specifications.'),
          $xaction);
        continue;
      }

      if (!phutil_is_natural_list($new_value)) {
        $errors[] = $this->newInvalidError(
          pht('Panels must be a list, not a map.'),
          $xaction);
        continue;
      }

      $new_phids = array();
      $seen_keys = array();
      foreach ($new_value as $idx => $spec) {
        if (!is_array($spec)) {
          $errors[] = $this->newInvalidError(
            pht(
              'Each panel specification must be a map of panel attributes. '.
              'Panel specification at index "%s" is "%s".',
              $idx,
              phutil_describe_type($spec)),
            $xaction);
          continue;
        }

        try {
          PhutilTypeSpec::checkMap(
            $spec,
            array(
              'panelPHID' => 'string',
              'columnKey' => 'string',
              'panelKey' => 'string',
            ));
        } catch (PhutilTypeCheckException $ex) {
          $errors[] = $this->newInvalidError(
            pht(
              'Panel specification at index "%s" is invalid: %s',
              $idx,
              $ex->getMessage()),
            $xaction);
          continue;
        }

        $panel_key = $spec['panelKey'];

        if (!strlen($panel_key)) {
          $errors[] = $this->newInvalidError(
            pht(
              'Panel specification at index "%s" has bad panel key "%s". '.
              'Panel keys must be nonempty.',
              $idx,
              $panel_key),
            $xaction);
          continue;
        }

        if (isset($seen_keys[$panel_key])) {
          $errors[] = $this->newInvalidError(
            pht(
              'Panel specification at index "%s" has duplicate panel key '.
              '"%s". Each panel must have a unique panel key.',
              $idx,
              $panel_key),
            $xaction);
          continue;
        }

        $seen_keys[$panel_key] = true;

        $panel_phid = $spec['panelPHID'];
        $new_phids[] = $panel_phid;

        $column_key = $spec['columnKey'];

        if (!isset($columns[$column_key])) {
          $errors[] = $this->newInvalidError(
            pht(
              'Panel specification at index "%s" has bad column key "%s", '.
              'valid column keys are: %s.',
              $idx,
              $column_key,
              implode(', ', array_keys($columns))),
            $xaction);
          continue;
        }
      }

      $new_phids = array_fuse($new_phids);
      $add_phids = array_diff_key($new_phids, $old_phids);

      if ($add_phids) {
        $panels = id(new PhabricatorDashboardPanelQuery())
          ->setViewer($actor)
          ->withPHIDs($add_phids)
          ->execute();
        $panels = mpull($panels, null, 'getPHID');

        foreach ($add_phids as $add_phid) {
          $panel = idx($panels, $add_phid);

          if (!$panel) {
            $errors[] = $this->newInvalidError(
              pht(
                'Panel specification adds panel "%s", but this is not a '.
                'valid panel or not a visible panel. You can only add '.
                'valid panels which you have permission to see to a dashboard.',
                $add_phid));
            continue;
          }
        }
      }
    }

    return $errors;
  }

}
