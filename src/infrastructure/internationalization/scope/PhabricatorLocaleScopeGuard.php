<?php

/**
 * Change the effective locale for the lifetime of this guard.
 *
 * Use @{method:PhabricatorEnv::beginScopedLocale} to acquire a guard.
 * Guards are released when they exit scope.
 */
final class PhabricatorLocaleScopeGuard
  extends Phobject {

  private static $stack = array();
  private $key;
  private $destroyed;

  public function __construct($code) {
    // If this is the first time we're building a guard, push the default
    // locale onto the bottom of the stack. We'll never remove it.
    if (empty(self::$stack)) {
      self::$stack[] = PhabricatorEnv::getLocaleCode();
    }

    // If there's no locale, use the server default locale.
    if (!$code) {
      $code = self::$stack[0];
    }

    // Push this new locale onto the stack and set it as the active locale.
    // We keep track of which key this guard owns, in case guards are destroyed
    // out-of-order.
    self::$stack[] = $code;
    $this->key = last_key(self::$stack);

    PhabricatorEnv::setLocaleCode($code);
  }

  public function __destruct() {
    if ($this->destroyed) {
      return;
    }
    $this->destroyed = true;

    // Remove this locale from the stack and set the new active locale. Usually,
    // we're the last item on the stack, so this shortens the stack by one item
    // and sets the locale underneath. However, it's possible that guards are
    // being destroyed out of order, so we might just be removing an item
    // somewhere in the middle of the stack. In this case, we won't actually
    // change the locale, just set it to its current value again.

    unset(self::$stack[$this->key]);
    PhabricatorEnv::setLocaleCode(end(self::$stack));
  }

}
