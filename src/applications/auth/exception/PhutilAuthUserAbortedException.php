<?php

/**
 * The user aborted the authentication workflow, by clicking "Cancel" or "Deny"
 * or taking some similar action.
 *
 * For example, in OAuth/OAuth2 workflows, the authentication provider
 * generally presents the user with a confirmation dialog with two options,
 * "Approve" and "Deny".
 *
 * If an adapter detects that the user has explicitly bailed out of the
 * workflow, it should throw this exception.
 */
final class PhutilAuthUserAbortedException extends PhutilAuthException {}
