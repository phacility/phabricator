<?php

final class PhabricatorPolicies extends PhabricatorPolicyConstants {

  const POLICY_PUBLIC   = 'public';
  const POLICY_USER     = 'users';
  const POLICY_ADMIN    = 'admin';
  const POLICY_NOONE    = 'no-one';

  /**
   * Returns the most public policy this install's configuration permits.
   * This is either "public" (if available) or "all users" (if not).
   *
   * @return const Most open working policy constant.
   */
  public static function getMostOpenPolicy() {
    if (PhabricatorEnv::getEnvConfig('policy.allow-public')) {
      return self::POLICY_PUBLIC;
    } else {
      return self::POLICY_USER;
    }
  }


}
