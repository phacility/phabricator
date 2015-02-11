<?php

/**
 * Exception raised when the user is already registered and the invite is a
 * no-op.
 */
final class PhabricatorAuthInviteRegisteredException
  extends PhabricatorAuthInviteException {}
