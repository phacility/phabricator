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
final class LiskDAOSet {
  private $daos = array();
  private $relatives = array();
  private $subsets = array();

  public function addToSet(LiskDAO $dao) {
    $this->daos[] = $dao;
    $dao->putInSet($this);
    return $this;
  }

  /**
   * The main purpose of this method is to break cyclic dependency.
   * It removes all objects from this set and all subsets created by it.
   */
  final public function clearSet() {
    $this->daos = array();
    $this->relatives = array();
    foreach ($this->subsets as $set) {
      $set->clearSet();
    }
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
