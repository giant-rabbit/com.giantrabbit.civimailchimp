<?php

// require_once 'CiviTest/CiviUnitTestCase.php';
require_once 'tests/phpunit/CiviTest/CiviUnitTestCase.php';

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
  
  static function mailchimpWebhookSampleRequestUpemail() {
    return array(
      'new_email' => 'anemirovsky+1234mailchimp@giantrabbit.com',
      'old_email' => 'anemirovsky+123mailchimp@giantrabbit.com',
      'list_id' => '35cb81331a',
    );
  }
}
