<?php

/*
 * Copyright 2011 Facebook, Inc.
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
 * Guard writes against CSRF. The Aphront structure takes care of most of this
 * for you, you just need to call:
 *
 *    AphrontWriteGuard::willWrite();
 *
 * ...before executing a write against any new kind of storage engine. MySQL
 * databases and the default file storage engines are already covered, but if
 * you introduce new types of datastores make sure their writes are guarded. If
 * you don't guard writes and make a mistake doing CSRF checks in a controller,
 * a CSRF vulnerability can escape undetected.
 *
 * If you need to execute writes on a page which doesn't have CSRF tokens (for
 * example, because you need to do logging), you can temporarily disable the
 * write guard by calling:
 *
 *    AphrontWriteGuard::beginUnguardedWrites();
 *    do_logging_write();
 *    AphrontWriteGuard::endUnguardedWrites();
 *
 * This is dangerous, because it disables the backup layer of CSRF protection
 * this class provides. You should need this only very, very rarely.
 *
 * @task protect  Protecting Writes
 * @task disable  Disabling Protection
 * @task manage   Managing Write Guards
 * @task internal Internals
 *
 * @group aphront
 */
final class AphrontWriteGuard {

  private static $instance;
  private static $allowUnguardedWrites = false;

  private $request;
  private $allowDepth = 0;


/* -(  Managing Write Guards  )---------------------------------------------- */


  /**
   * Construct a new write guard for a request. Only one write guard may be
   * active at a time. You must explicitly call @{method:dispose} when you are
   * done with a write guard:
   *
   *    $guard = new AphrontWriteGuard();
   *    // ...
   *    $guard->dispose();
   *
   * Normally, you do not need to manage guards yourself -- the Aphront stack
   * handles it for you.
   *
   * @param   AphrontRequest  Request to read CSRF token information from.
   * @return  this
   * @task    manage
   */
  public function __construct(AphrontRequest $request) {
    if (self::$instance) {
      throw new Exception(
        "An AphrontWriteGuard already exists. Dispose of the previous guard ".
        "before creating a new one.");
    }
    if (self::$allowUnguardedWrites) {
      throw new Exception(
        "An AphrontWriteGuard is being created in a context which permits ".
        "unguarded writes unconditionally. This is not allowed and indicates ".
        "a serious error.");
    }
    $this->request = $request;
    self::$instance = $this;
  }


  /**
   * Dispose of the active write guard. You must call this method when you are
   * done with a write guard. You do not normally need to call this yourself.
   *
   * @return void
   * @task manage
   */
  public function dispose() {
    if ($this->allowDepth > 0) {
      throw new Exception(
        "Imbalanced AphrontWriteGuard: more beginUnguardedWrites() calls than ".
        "endUnguardedWrites() calls.");
    }
    self::$instance = null;
  }


/* -(  Protecting Writes  )-------------------------------------------------- */


  /**
   * Declare intention to perform a write, validating that writes are allowed.
   * You should call this method before executing a write whenever you implement
   * a new storage engine where information can be permanently kept.
   *
   * Writes are permitted if:
   *
   *   - The request has valid CSRF tokens.
   *   - Unguarded writes have been temporarily enabled by a call to
   *     @{method:beginUnguardedWrites}.
   *   - All write guarding has been disabled with
   *     @{method:allowDangerousUnguardedWrites}.
   *
   * If none of these conditions are true, this method will throw and prevent
   * the write.
   *
   * @return void
   * @task protect
   */
  public static function willWrite() {
    if (!self::$instance) {
      if (!self::$allowUnguardedWrites) {
        throw new Exception(
          "Unguarded write! There must be an active AphrontWriteGuard to ".
          "perform writes.");
      } else {
        // Unguarded writes are being allowed unconditionally.
        return;
      }
    }

    $instance = self::$instance;

    if ($instance->allowDepth == 0) {
      $instance->request->validateCSRF();
    }
  }


/* -(  Disabling Write Protection  )----------------------------------------- */


  /**
   * Enter a scope which permits unguarded writes. This works like
   * @{method:beginUnguardedWrites} but returns an object which will end
   * the unguarded write scope when its __destruct() method is called. This
   * is useful to more easily handle exceptions correctly in unguarded write
   * blocks:
   *
   *   // Restores the guard even if do_logging() throws.
   *   function unguarded_scope() {
   *     $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
   *     do_logging();
   *   }
   *
   * @return AphrontScopedUnguardedWriteCapability Object which ends unguarded
   *            writes when it leaves scope.
   * @task disable
   */
  public static function beginScopedUnguardedWrites() {
    self::beginUnguardedWrites();
    return new AphrontScopedUnguardedWriteCapability();
  }


  /**
   * Begin a block which permits unguarded writes. You should use this very
   * sparingly, and only for things like logging where CSRF is not a concern.
   *
   * You must pair every call to @{method:beginUnguardedWrites} with a call to
   * @{method:endUnguardedWrites}:
   *
   *   AphrontWriteGuard::beginUnguardedWrites();
   *   do_logging();
   *   AphrontWriteGuard::endUnguardedWrites();
   *
   * @return void
   * @task disable
   */
  public static function beginUnguardedWrites() {
    if (!self::$instance) {
      return;
    }
    self::$instance->allowDepth++;
  }

  /**
   * Declare that you have finished performing unguarded writes. You must
   * call this exactly once for each call to @{method:beginUnguardedWrites}.
   *
   * @return void
   * @task disable
   */
  public static function endUnguardedWrites() {
    if (!self::$instance) {
      return;
    }
    if (self::$instance->allowDepth <= 0) {
      throw new Exception(
        "Imbalanced AphrontWriteGuard: more endUnguardedWrites() calls than ".
        "beginUnguardedWrites() calls.");
    }
    self::$instance->allowDepth--;
  }


  /**
   * Allow execution of unguarded writes. This is ONLY appropriate for use in
   * script contexts or other contexts where you are guaranteed to never be
   * vulnerable to CSRF concerns. Calling this method is EXTREMELY DANGEROUS
   * if you do not understand the consequences.
   *
   * If you need to perform unguarded writes on an otherwise guarded workflow
   * which is vulnerable to CSRF, use @{method:beginUnguardedWrites}.
   *
   * @return void
   * @task disable
   */
  public static function allowDangerousUnguardedWrites($allow) {
    if (self::$instance) {
      throw new Exception(
        "You can not unconditionally disable AphrontWriteGuard by calling ".
        "allowDangerousUnguardedWrites() while a write guard is active. Use ".
        "beginUnguardedWrites() to temporarily allow unguarded writes.");
    }
    self::$allowUnguardedWrites = true;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * When the object is destroyed, make sure @{method:dispose} was called.
   */
  public function __destruct() {
    if (isset(self::$instance)) {
      throw new Exception(
        "AphrontWriteGuard was not properly disposed of! Call dispose() on ".
        "every AphrontWriteGuard object you instantiate.");
    }
  }
}
