<?php

require_once 'CiviTest/CiviUnitTestCase.php';

class CRM_CiviMailchimp_Page_SyncLogTest extends CiviUnitTestCase {

  static function setUpBeforeClass() {
    // Use the Mailchimp API test class for all the tests.
    CRM_Core_BAO_Setting::setItem('CRM_MailchimpMock', 'CiviMailchimp Preferences', 'mailchimp_api_class');
  }

  function setUp() {
    parent::setUp();
  }

  function tearDown() {
    // Normally, we wouldn't want to truncate any tables as it makes running
    // the tests slower and opens the door for writing test that aren't self-
    // sufficient, but we're forced into this as CiviUnitTestCase forces a 
    // quickCleanup on civicrm_contact in its tearDown. :(
    $this->quickCleanup(array('civicrm_email', 'civicrm_queue_item', 'civimailchimp_sync_settings', 'civimailchimp_interest_groups_sync_settings', 'civimailchimp_sync_log'));
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    parent::tearDown();
  }

  function testGetAllRows() {
    $mailchimp_sync_log = CRM_CiviMailchimp_BAO_SyncLogTest::createTestLogMessage('This is a test error message');
    $details = unserialize($mailchimp_sync_log->details);
    $details = print_r($details, TRUE);
    $timestamp = date('c', $mailchimp_sync_log->timestamp);
    $timestamp = CRM_Utils_Date::customFormat($timestamp);
    $rows = CRM_CiviMailchimp_Page_SyncLog::getAllRows();
    $this->assertCount(1, $rows);
    $this->assertEquals($mailchimp_sync_log->id, $rows[0]['id']);
    $this->assertEquals('Error', $rows[0]['type']);
    $this->assertEquals('CiviCRM to Mailchimp', $rows[0]['direction']);
    $this->assertEquals($mailchimp_sync_log->message, $rows[0]['message']);
    $this->assertEquals($details, $rows[0]['details']);
    $this->assertEquals($timestamp, $rows[0]['timestamp']);
  }
}
