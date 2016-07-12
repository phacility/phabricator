<?php

/**
 * You usually don't need to use this class directly as it is controlled by
 * @{class:LiskDAO}. You can create it if you want to work with objects of same
 * type from different sources as with one set. Let's say you want to get
 * e-mails of all users involved in a revision:
 *
 *   $users = new LiskDAOSet();
 *   $users->addToSet($author);
 *   foreach ($reviewers as $reviewer) {
 *     $users->addToSet($reviewer);
 *   }
 *   foreach ($ccs as $cc) {
 *     $users->addToSet($cc);
 *   }
 *   // Preload e-mails of all involved users and return e-mails of author.
 *   $author_emails = $author->loadRelatives(
 *     new PhabricatorUserEmail(),
 *     'userPHID',
 *     'getPHID');
 */
final class LiskDAOSet extends Phobject {
  private $daos = array();
  private $relatives = array();
  private $subsets = array();

  public function addToSet(LiskDAO $dao) {
    if ($this->relatives) {
      throw new Exception(
        pht(
          "Don't call %s after loading data!",
          __FUNCTION__.'()'));
    }
    $this->daos[] = $dao;
    $dao->putInSet($this);
    return $this;
  }

  /**
   * The main purpose of this method is to break cyclic dependency.
   * It removes all objects from this set and all subsets created by it.
   */
  public function clearSet() {
    $this->daos = array();
    $this->relatives = array();
    foreach ($this->subsets as $set) {
      $set->clearSet();
    }
    $this->subsets = array();
    return $this;
  }


  /**
   * See @{method:LiskDAO::loadRelatives}.
   */
  public function loadRelatives(
    LiskDAO $object,
    $foreign_column,
    $key_method = 'getID',
    $where = '') {

    $relatives = &$this->relatives[
      get_class($object)."-{$foreign_column}-{$key_method}-{$where}"];

    if ($relatives === null) {
      $ids = array();
      foreach ($this->daos as $dao) {
        $id = $dao->$key_method();
        if ($id !== null) {
          $ids[$id] = $id;
        }
      }
      if (!$ids) {
        $relatives = array();
      } else {
        $set = new LiskDAOSet();
        $this->subsets[] = $set;
        $relatives = $object->putInSet($set)->loadAllWhere(
          '%C IN (%Ls) %Q',
          $foreign_column,
          $ids,
          ($where != '' ? 'AND '.$where : ''));
        $relatives = mgroup($relatives, 'get'.$foreign_column);
      }
    }

    return $relatives;
  }

}
