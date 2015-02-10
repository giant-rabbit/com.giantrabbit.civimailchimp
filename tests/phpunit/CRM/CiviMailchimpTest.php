<?php
/**
 * @file
 * A virtual MailChimp API implementation for use in testing.
 */

require 'CiviMailchimp/ListsTest.php';

class CRM_CiviMailchimpTest {

  public $lists;

  public function __construct($apikey = null, $opts = array()) {
    $this->lists = new CRM_CiviMailchimp_ListsTest($this);
  }
}
