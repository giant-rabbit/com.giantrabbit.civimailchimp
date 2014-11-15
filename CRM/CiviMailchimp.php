<?php

/**
 * @file
 * Wrapper class around the Mailchimp API.
 */

/**
 * Class CRM_CiviMailchimp
 *
 * Extend the MailChimp class to add some CiviCRM specific code.
 */
class CRM_CiviMailchimp extends Mailchimp {

  /**
   * Override Mailchimp's default log function to write to CiviCRM's log.
   */
  public function log($msg) {
    if ($this->debug) {
      CRM_Core_Error::debug_log_message($msg);
    }
  }
}
