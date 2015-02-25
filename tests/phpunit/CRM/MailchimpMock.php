<?php
/**
 * @file
 * A virtual MailChimp API implementation for use in testing.
 */

require 'MailchimpMock/ListsMock.php';

class CRM_MailchimpMock {

  public $lists;

  public function __construct($apikey = null, $opts = array()) {
    $this->lists = new CRM_MailchimpMock_ListsMock($this);
  }
}
