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

  static function mailchimpWebhookSampleRequest($request_type) {
    $function_name = 'self::mailchimpWebhookSampleRequest' . ucwords($request_type);
    if (is_callable($function_name)) {
      return call_user_func($function_name);
    }
  }

  static function mailchimpWebhookSampleRequestSubscribe() {
    return array(
      'email' => 'civimailchimp+test@civimailchimp.org',
      'merges' => array(
        'EMAIL' => 'civimailchimp+test@civimailchimp.org',
        'FNAME' => 'Civi',
        'LNAME' => 'Mailchimp',
      ),
      'list_id' => '35cb81331a',
    );
  }

  static function mailchimpWebhookSampleRequestUnsubscribe() {
    return array(
      'email' => 'civimailchimp+test@civimailchimp.org',
      'list_id' => '35cb81331a',
    );
  }
  
  static function mailchimpWebhookSampleRequestUpemail() {
    return array(
      'new_email' => 'civimailchimp+test123@civimailchimp.org',
      'old_email' => 'civimailchimp+test@civimailchimp.org',
      'list_id' => '35cb81331a',
    );
  }

  static function mailchimpWebhookSampleRequestProfile() {
    return array(
      'email' => 'civimailchimp+test@civimailchimp.org',
      'merges' => array(
        'EMAIL' => 'civimailchimp+test@civimailchimp.org',
        'FNAME' => 'CiviUpdated',
        'LNAME' => 'MailchimpUpdated',
      ),
      'list_id' => '35cb81331a',
    );
  }

  static function mailchimpWebhookSampleRequestCleaned() {
    return array(
      'email' => 'civimailchimp+test@civimailchimp.org',
      'list_id' => '35cb81331a',
    );
  }
}
