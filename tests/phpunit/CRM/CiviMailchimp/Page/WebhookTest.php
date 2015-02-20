<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Tests for Mailchimp Webhooks.
 */
class CRM_CiviMailchimp_Page_WebhookTest extends CiviUnitTestCase {
  function setUp() {
    // If your test manipulates any SQL tables, then you should truncate
    // them to ensure a consisting starting point for all tests
    // $this->quickCleanup(array('example_table_name'));
    parent::setUp();
  }

  function tearDown() {
    parent::tearDown();
  }

  static function sampleRequestSubscribeOrProfileUpdate() {
    $rand = rand();
    return array(
      'email' => "civimailchimp_test+{$rand}@civimailchimp.org",
      'merges' => array(
        'EMAIL' => "civimailchimp_test+{$rand}@civimailchimp.org",
        'FNAME' => "Civi{$rand}",
        'LNAME' => "Mailchimp{$rand}",
      ),
      'list_id' => 'MailchimpListsTestListA',
    );
  }

  static function sampleRequestUnsubscribeOrCleaned() {
    $rand = rand();
    return array(
      'email' => "civimailchimp_test+{$rand}@civimailchimp.org",
      'list_id' => 'MailchimpListsTestListA',
    );
  }
  
  static function sampleRequestUpemail() {
    $rand = rand();
    return array(
      'new_email' => "civimailchimp_test_new+{$rand}@civimailchimp.org",
      'old_email' => "civimailchimp_test+{$rand}@civimailchimp.org",
      'list_id' => 'MailchimpListsTestListA',
    );
  }
}
