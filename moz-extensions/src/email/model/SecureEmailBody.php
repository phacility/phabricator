<?php


/**
 * All email event bodies that are stripped of sensitive information must implement this interface. Emails for
 * secure revisions will use the more-limited information of their associated SecureEmailBody.
 */
interface SecureEmailBody {

}