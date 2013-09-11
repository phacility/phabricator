<?php

final class PhabricatorApplicationTransactionPHIDTypeTransaction
  extends PhabricatorPHIDType {

  const TYPECONST = 'XACT';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Transaction');
  }

  public function newObject() {
    // NOTE: We could produce an object here, but we'd need to take a PHID type
    // and subtype to do so. Currently, we never write edges to transactions,
    // so leave this unimplemented for the moment.
    return null;
  }

  public function loadObjects(
    PhabricatorObjectQuery $object_query,
    array $phids) {

    static $queries;
    if ($queries === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass('PhabricatorApplicationTransactionQuery')
        ->loadObjects();

      $queries = array();
      foreach ($objects as $object) {
        $type = $object
          ->getTemplateApplicationTransaction()
          ->getApplicationTransactionType();

        $queries[$type] = $object;
      }
    }

    $phid_subtypes = array();
    foreach ($phids as $phid) {
      $subtype = phid_get_subtype($phid);
      if ($subtype) {
        $phid_subtypes[$subtype][] = $phid;
      }
    }

    $results = array();
    foreach ($phid_subtypes as $subtype => $subtype_phids) {
      $query = idx($queries, $subtype);
      if (!$query) {
        continue;
      }

      $xactions = id(clone $query)
        ->setViewer($object_query->getViewer())
        ->setParentQuery($object_query)
        ->withPHIDs($subtype_phids)
        ->execute();

      $results += mpull($xactions, null, 'getPHID');
    }

    return $results;
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    // NOTE: We don't produce meaningful handles here because they're
    // impractical to produce and no application uses them.

  }

}
