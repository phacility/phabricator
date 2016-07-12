<?php

abstract class NuanceManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function loadSource(PhutilArgumentParser $argv, $key) {
    $source = $argv->getArg($key);
    if (!strlen($source)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a source with %s.',
          '--'.$key));
    }

    $query = id(new NuanceSourceQuery())
      ->setViewer($this->getViewer())
      ->setRaisePolicyExceptions(true);

    $type_unknown = PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN;

    if (ctype_digit($source)) {
      $kind = 'id';
      $query->withIDs(array($source));
    } else if (phid_get_type($source) !== $type_unknown) {
      $kind = 'phid';
      $query->withPHIDs($source);
    } else {
      $kind = 'name';
      $query->withNameNgrams($source);
    }

    $sources = $query->execute();

    if (!$sources) {
      switch ($kind) {
        case 'id':
          $message = pht(
            'No source exists with ID "%s".',
            $source);
          break;
        case 'phid':
          $message = pht(
            'No source exists with PHID "%s".',
            $source);
          break;
        default:
          $message = pht(
            'No source exists with a name matching "%s".',
            $source);
          break;
      }

      throw new PhutilArgumentUsageException($message);
    } else if (count($sources) > 1) {
      $message = pht(
        'More than one source matches "%s". Choose a narrower query, or '.
        'use an ID or PHID to select a source. Matching sources: %s.',
        $source,
        implode(', ', mpull($sources, 'getName')));

      throw new PhutilArgumentUsageException($message);
    }

    return head($sources);
  }

  protected function loadITem(PhutilArgumentParser $argv, $key) {
    $item = $argv->getArg($key);
    if (!strlen($item)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a item with %s.',
          '--'.$key));
    }

    $query = id(new NuanceItemQuery())
      ->setViewer($this->getViewer())
      ->setRaisePolicyExceptions(true);

    $type_unknown = PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN;

    if (ctype_digit($item)) {
      $kind = 'id';
      $query->withIDs(array($item));
    } else if (phid_get_type($item) !== $type_unknown) {
      $kind = 'phid';
      $query->withPHIDs($item);
    } else {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify the ID or PHID of an item to update. Parameter "%s" '.
          'is not an ID or PHID.',
          $item));
    }

    $items = $query->execute();

    if (!$items) {
      switch ($kind) {
        case 'id':
          $message = pht(
            'No item exists with ID "%s".',
            $item);
          break;
        case 'phid':
          $message = pht(
            'No item exists with PHID "%s".',
            $item);
          break;
      }

      throw new PhutilArgumentUsageException($message);
    }

    return head($items);
  }
}
