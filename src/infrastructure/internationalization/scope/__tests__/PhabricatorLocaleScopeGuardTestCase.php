<?php

final class PhabricatorLocaleScopeGuardTestCase
  extends PhabricatorTestCase {

  public function testLocaleScopeGuard() {
    $original = PhabricatorEnv::getLocaleCode();

    // Set a guard; it should change the locale, then revert it when destroyed.
    $guard = PhabricatorEnv::beginScopedLocale('en_GB');
    $this->assertEqual('en_GB', PhabricatorEnv::getLocaleCode());
    unset($guard);
    $this->assertEqual($original, PhabricatorEnv::getLocaleCode());

    // Nest guards, then destroy them out of order.
    $guard1 = PhabricatorEnv::beginScopedLocale('en_GB');
    $this->assertEqual('en_GB', PhabricatorEnv::getLocaleCode());
    $guard2 = PhabricatorEnv::beginScopedLocale('en_A*');
    $this->assertEqual('en_A*', PhabricatorEnv::getLocaleCode());
    unset($guard1);
    $this->assertEqual('en_A*', PhabricatorEnv::getLocaleCode());
    unset($guard2);
    $this->assertEqual($original, PhabricatorEnv::getLocaleCode());

    // If you push `null`, that should mean "the default locale", not
    // "the current locale".
    $guard3 = PhabricatorEnv::beginScopedLocale('en_GB');
    $this->assertEqual('en_GB', PhabricatorEnv::getLocaleCode());
    $guard4 = PhabricatorEnv::beginScopedLocale(null);
    $this->assertEqual($original, PhabricatorEnv::getLocaleCode());
    unset($guard4);
    $this->assertEqual('en_GB', PhabricatorEnv::getLocaleCode());
    unset($guard3);
    $this->assertEqual($original, PhabricatorEnv::getLocaleCode());

  }

}
