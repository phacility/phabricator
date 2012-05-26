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
 * Editor class for creating and adjusting users. This class guarantees data
 * integrity and writes logs when user information changes.
 *
 * @task config     Configuration
 * @task edit       Creating and Editing Users
 * @task role       Editing Roles
 * @task email      Adding, Removing and Changing Email
 * @task internal   Internals
 */
final class PhabricatorUserEditor {

  private $actor;
  private $logs = array();


/* -(  Configuration  )------------------------------------------------------ */


  /**
   * @task config
   */
  public function setActor(PhabricatorUser $actor) {
    $this->actor = $actor;
    return $this;
  }


/* -(  Creating and Editing Users  )----------------------------------------- */


  /**
   * @task edit
   */
  public function createNewUser(
    PhabricatorUser $user,
    PhabricatorUserEmail $email) {

    if ($user->getID()) {
      throw new Exception("User has already been created!");
    }

    if ($email->getID()) {
      throw new Exception("Email has already been created!");
    }

    // Always set a new user's email address to primary.
    $email->setIsPrimary(1);

    $this->willAddEmail($email);

    $user->openTransaction();
      $user->save();

      $email->setUserPHID($user->getPHID());

      try {
        $email->save();
      } catch (AphrontQueryDuplicateKeyException $ex) {
        $user->killTransaction();
        throw $ex;
      }

      $log = PhabricatorUserLog::newLog(
        $this->actor,
        $user,
        PhabricatorUserLog::ACTION_CREATE);
      $log->setNewValue($email->getAddress());
      $log->save();

    $user->saveTransaction();

    return $this;
  }


  /**
   * @task edit
   */
  public function updateUser(PhabricatorUser $user) {
    if (!$user->getID()) {
      throw new Exception("User has not been created yet!");
    }

    $actor = $this->requireActor();
    $user->openTransaction();
      $user->save();

      $log = PhabricatorUserLog::newLog(
        $actor,
        $user,
        PhabricatorUserLog::ACTION_EDIT);
      $log->save();

    $user->saveTransaction();

    return $this;
  }


  /**
   * @task edit
   */
  public function changePassword(PhabricatorUser $user, $password) {
    if (!$user->getID()) {
      throw new Exception("User has not been created yet!");
    }

    $user->openTransaction();
      $user->reload();

      $user->setPassword($password);
      $user->save();

      $log = PhabricatorUserLog::newLog(
        $this->actor,
        $user,
        PhabricatorUserLog::ACTION_CHANGE_PASSWORD);
      $log->save();

    $user->saveTransaction();
  }


/* -(  Editing Roles  )------------------------------------------------------ */


  /**
   * @task role
   */
  public function makeAdminUser(PhabricatorUser $user, $admin) {
    $actor = $this->requireActor();

    if (!$user->getID()) {
      throw new Exception("User has not been created yet!");
    }

    $user->openTransaction();
      $user->beginWriteLocking();

        $user->reload();
        if ($user->getIsAdmin() == $admin) {
          $user->endWriteLocking();
          $user->killTransaction();
          return $this;
        }

        $log = PhabricatorUserLog::newLog(
          $actor,
          $user,
          PhabricatorUserLog::ACTION_ADMIN);
        $log->setOldValue($user->getIsAdmin());
        $log->setNewValue($admin);

        $user->setIsAdmin($admin);
        $user->save();

        $log->save();

      $user->endWriteLocking();
    $user->saveTransaction();

    return $this;
  }

  /**
   * @task role
   */
  public function disableUser(PhabricatorUser $user, $disable) {
    $actor = $this->requireActor();

    if (!$user->getID()) {
      throw new Exception("User has not been created yet!");
    }

    $user->openTransaction();
      $user->beginWriteLocking();

        $user->reload();
        if ($user->getIsDisabled() == $disable) {
          $user->endWriteLocking();
          $user->killTransaction();
          return $this;
        }

        $log = PhabricatorUserLog::newLog(
          $actor,
          $user,
          PhabricatorUserLog::ACTION_DISABLE);
        $log->setOldValue($user->getIsDisabled());
        $log->setNewValue($disable);

        $user->setIsDisabled($disable);
        $user->save();

        $log->save();

      $user->endWriteLocking();
    $user->saveTransaction();

    return $this;
  }


/* -(  Adding, Removing and Changing Email  )-------------------------------- */


  /**
   * @task email
   */
  public function addEmail(
    PhabricatorUser $user,
    PhabricatorUserEmail $email) {

    $actor = $this->requireActor();

    if (!$user->getID()) {
      throw new Exception("User has not been created yet!");
    }
    if ($email->getID()) {
      throw new Exception("Email has already been created!");
    }

    // Use changePrimaryEmail() to change primary email.
    $email->setIsPrimary(0);
    $email->setUserPHID($user->getPHID());

    $this->willAddEmail($email);

    $user->openTransaction();
      $user->beginWriteLocking();

        $user->reload();

        try {
          $email->save();
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $user->endWriteLocking();
          $user->killTransaction();

          throw $ex;
        }

        $log = PhabricatorUserLog::newLog(
          $this->actor,
          $user,
          PhabricatorUserLog::ACTION_EMAIL_ADD);
        $log->setNewValue($email->getAddress());
        $log->save();

      $user->endWriteLocking();
    $user->saveTransaction();

    return $this;
  }


  /**
   * @task email
   */
  public function removeEmail(
    PhabricatorUser $user,
    PhabricatorUserEmail $email) {

    $actor = $this->requireActor();

    if (!$user->getID()) {
      throw new Exception("User has not been created yet!");
    }
    if (!$email->getID()) {
      throw new Exception("Email has not been created yet!");
    }

    $user->openTransaction();
      $user->beginWriteLocking();

        $user->reload();
        $email->reload();

        if ($email->getIsPrimary()) {
          throw new Exception("Can't remove primary email!");
        }
        if ($email->getUserPHID() != $user->getPHID()) {
          throw new Exception("Email not owned by user!");
        }

        $email->delete();

        $log = PhabricatorUserLog::newLog(
          $this->actor,
          $user,
          PhabricatorUserLog::ACTION_EMAIL_REMOVE);
        $log->setOldValue($email->getAddress());
        $log->save();

      $user->endWriteLocking();
    $user->saveTransaction();

    return $this;
  }


  /**
   * @task email
   */
  public function changePrimaryEmail(
    PhabricatorUser $user,
    PhabricatorUserEmail $email) {
    $actor = $this->requireActor();

    if (!$user->getID()) {
      throw new Exception("User has not been created yet!");
    }
    if (!$email->getID()) {
      throw new Exception("Email has not been created yet!");
    }

    $user->openTransaction();
      $user->beginWriteLocking();

        $user->reload();
        $email->reload();

        if ($email->getUserPHID() != $user->getPHID()) {
          throw new Exception("User does not own email!");
        }

        if ($email->getIsPrimary()) {
          throw new Exception("Email is already primary!");
        }

        if (!$email->getIsVerified()) {
          throw new Exception("Email is not verified!");
        }

        $old_primary = $user->loadPrimaryEmail();
        if ($old_primary) {
          $old_primary->setIsPrimary(0);
          $old_primary->save();
        }

        $email->setIsPrimary(1);
        $email->save();

        $log = PhabricatorUserLog::newLog(
          $actor,
          $user,
          PhabricatorUserLog::ACTION_EMAIL_PRIMARY);
        $log->setOldValue($old_primary ? $old_primary->getAddress() : null);
        $log->setNewValue($email->getAddress());

        $log->save();

      $user->endWriteLocking();
    $user->saveTransaction();

    if ($old_primary) {
      $old_primary->sendOldPrimaryEmail($user, $email);
    }
    $email->sendNewPrimaryEmail($user);

    return $this;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  private function requireActor() {
    if (!$this->actor) {
      throw new Exception("User edit requires actor!");
    }
    return $this->actor;
  }


  /**
   * @task internal
   */
  private function willAddEmail(PhabricatorUserEmail $email) {

    // Hard check before write to prevent creation of disallowed email
    // addresses. Normally, the application does checks and raises more
    // user friendly errors for us, but we omit the courtesy checks on some
    // pathways like administrative scripts for simplicity.

    if (!PhabricatorUserEmail::isAllowedAddress($email->getAddress())) {
      throw new Exception(PhabricatorUserEmail::describeAllowedAddresses());
    }
  }

}
