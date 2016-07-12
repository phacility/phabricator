<?php

/**
 * Exception raised when the user must log in to continue with the invite
 * workflow (for example, the because the email address is already bound to an
 * account).
 */
final class PhabricatorAuthInviteLoginException
  extends PhabricatorAuthInviteDialogException {}
