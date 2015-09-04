<?php

require_once 'CiviTest/CiviUnitTestCase.php';

class CRM_CiviMailchimp_BAO_SyncLogTest extends CiviUnitTestCase {

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

  function testSaveMessage() {
    $type = 'error';
    $direction = 'civicrm_to_mailchimp';
    $message = 'This is an error.';
    $details = array('error_details' => TRUE);
    $expected_mailchimp_sync_log = CRM_CiviMailchimp_BAO_SyncLog::saveMessage($type, $direction, $message, $details);
    $mailchimp_sync_log = CRM_CiviMailchimp_BAO_SyncLog::findById($expected_mailchimp_sync_log->id);
    $this->assertEquals($expected_mailchimp_sync_log->id, $mailchimp_sync_log->id);
    $this->assertEquals($expected_mailchimp_sync_log->type, $mailchimp_sync_log->type);
    $this->assertEquals($expected_mailchimp_sync_log->direction, $mailchimp_sync_log->direction);
    $this->assertEquals($expected_mailchimp_sync_log->message, $mailchimp_sync_log->message);
    $this->assertEquals($expected_mailchimp_sync_log->details, $mailchimp_sync_log->details);
    $this->assertEquals($expected_mailchimp_sync_log->timestamp, $mailchimp_sync_log->timestamp);
  }

  function testGetLatestUnclearedCiviToMailchimpErrorMessage() {
    $mailchimp_sync_log = self::createTestLogMessage('This is a test error message', array('error_details' => TRUE));
    $expected_message = 'This is a test error message<br /><br />Additional details can be found at the <a href="http://FIX ME/index.php?q=civicrm/admin/mailchimp/log">CiviMailchimp Sync Log</a>.<br /><br /><a class="clear-link" href="/index.php?q=civicrm/admin/mailchimp/log/clear-message&amp;id=1">Do not show this message again.</a>';
    $message = CRM_CiviMailchimp_BAO_SyncLog::getLatestUnclearedCiviToMailchimpErrorMessage();
    $this->assertEquals($expected_message, $message);
  }

  function testGetLatestUnclearedCiviToMailchimpErrorMessageSuccess() {
    $mailchimp_sync_log = self::createTestLogMessage('This is a test error message', array('error_details' => TRUE), 'civicrm_to_mailchimp', 'success');
    $message = CRM_CiviMailchimp_BAO_SyncLog::getLatestUnclearedCiviToMailchimpErrorMessage();
    $this->assertNull($message);
  }

  function testGetLatestUnclearedCiviToMailchimpErrorMessageCleared() {
    $mailchimp_sync_log = self::createTestLogMessage('This is a test error message', array('error_details' => TRUE));
    CRM_CiviMailchimp_BAO_SyncLog::clearMessage($mailchimp_sync_log->id);
    $message = CRM_CiviMailchimp_BAO_SyncLog::getLatestUnclearedCiviToMailchimpErrorMessage();
    $this->assertNull($message);
  }

  function testGetLatestUnclearedCiviToMailchimpErrorMessageWrongDirection() {
    $mailchimp_sync_log = self::createTestLogMessage('This is a test error message', array('error_details' => TRUE), 'mailchimp_to_civicrm');
    $message = CRM_CiviMailchimp_BAO_SyncLog::getLatestUnclearedCiviToMailchimpErrorMessage();
    $this->assertNull($message);
  }

  function testGetUnclearedMailchimpToCiviErrorMessages() {
    $mailchimp_sync_log = self::createTestLogMessage('This is a test error message', array('error_details' => TRUE), 'mailchimp_to_civicrm');
    $expected_message = 'This is a test error message<br /><br />Additional details can be found at the <a href="http://FIX ME/index.php?q=civicrm/admin/mailchimp/log">CiviMailchimp Sync Log</a>.<br /><br /><a class="clear-link" href="/index.php?q=civicrm/admin/mailchimp/log/clear-message&amp;id=1">Do not show this message again.</a>';
    $messages = CRM_CiviMailchimp_BAO_SyncLog::getUnclearedMailchimpToCiviErrorMessages();
    $this->assertCount(1, $messages);
    $this->assertEquals($expected_message, $messages[0]);
  }

  function testGetUnclearedMailchimpToCiviErrorMessagesSuccess() {
    $mailchimp_sync_log = self::createTestLogMessage('This is a test error message', array('error_details' => TRUE), 'mailchimp_to_civicrm', 'success');
    $messages = CRM_CiviMailchimp_BAO_SyncLog::getUnclearedMailchimpToCiviErrorMessages();
    $this->assertCount(0, $messages);
  }

  function testGetUnclearedMailchimpToCiviErrorMessagesCleared() {
    $mailchimp_sync_log = self::createTestLogMessage('This is a test error message', array('error_details' => TRUE), 'mailchimp_to_civicrm');
    CRM_CiviMailchimp_BAO_SyncLog::clearMessage($mailchimp_sync_log->id);
    $messages = CRM_CiviMailchimp_BAO_SyncLog::getUnclearedMailchimpToCiviErrorMessages();
    $this->assertCount(0, $messages);
  }

  function testGetUnclearedMailchimpToCiviErrorMessagesWrongDirection() {
    $mailchimp_sync_log = self::createTestLogMessage('This is a test error message', array('error_details' => TRUE), 'civicrm_to_mailchimp');
    $messages = CRM_CiviMailchimp_BAO_SyncLog::getUnclearedMailchimpToCiviErrorMessages();
    $this->assertCount(0, $messages);
  }

  function testFormatMessage() {
    $mailchimp_sync_log = self::createTestLogMessage('This is a test error message', array('error_details' => TRUE), 'civicrm_to_mailchimp');
    $expected_message = 'This is a test error message<br /><br />Additional details can be found at the <a href="http://FIX ME/index.php?q=civicrm/admin/mailchimp/log">CiviMailchimp Sync Log</a>.<br /><br /><a class="clear-link" href="/index.php?q=civicrm/admin/mailchimp/log/clear-message&amp;id=1">Do not show this message again.</a>';
    $message = CRM_CiviMailchimp_BAO_SyncLog::formatMessage($mailchimp_sync_log);
    $this->assertEquals($expected_message, $message);
  }

  function testDeleteOldMessages() {
    $i = 1;
    $mailchimp_sync_log = self::createTestLogMessage('This is a test error message');
    sleep(1);
    while ($i <= 100) {
      $mailchimp_sync_log = self::createTestLogMessage('This is a test error message');
      $i++;
    }
    CRM_CiviMailchimp_BAO_SyncLog::deleteOldMessages();
    // The entry with ID 1 is the oldest and should have been deleted.
    $this->setExpectedException('CRM_CiviMailchimp_Exception', 'Could not find CiviMailchimp Sync log message with ID 1.');
    $mailchimp_sync_log = CRM_CiviMailchimp_BAO_SyncLog::findById(1);
  }

  function testFindById() {
     $created_mailchimp_sync_log = self::createTestLogMessage('This is a test error message');
     $expected_mailchimp_sync_log = new CRM_CiviMailchimp_BAO_SyncLog();
     $expected_mailchimp_sync_log->id = $created_mailchimp_sync_log->id;
     $expected_mailchimp_sync_log->find(TRUE);
     $mailchimp_sync_log = CRM_CiviMailchimp_BAO_SyncLog::findById($created_mailchimp_sync_log->id);
     $this->assertEquals($expected_mailchimp_sync_log->id, $mailchimp_sync_log->id);
     $this->assertEquals($expected_mailchimp_sync_log->type, $mailchimp_sync_log->type);
     $this->assertEquals($expected_mailchimp_sync_log->direction, $mailchimp_sync_log->direction);
     $this->assertEquals($expected_mailchimp_sync_log->message, $mailchimp_sync_log->message);
     $this->assertEquals($expected_mailchimp_sync_log->details, $mailchimp_sync_log->details);
     $this->assertEquals($expected_mailchimp_sync_log->timestamp, $mailchimp_sync_log->timestamp);
  }

  function testFindByIdException() {
    $this->setExpectedException('CRM_CiviMailchimp_Exception', 'Could not find CiviMailchimp Sync log message with ID 1.');
    $mailchimp_sync_log = CRM_CiviMailchimp_BAO_SyncLog::findById(1);
  }

  function testClearMessage() {
    $existing_mailchimp_sync_log = self::createTestLogMessage('This is a test error message');
    CRM_CiviMailchimp_BAO_SyncLog::clearMessage($existing_mailchimp_sync_log->id);
    $mailchimp_sync_log = CRM_CiviMailchimp_BAO_SyncLog::findById($existing_mailchimp_sync_log->id);
    $this->assertEquals(0, $existing_mailchimp_sync_log->cleared);
    $this->assertEquals(1, $mailchimp_sync_log->cleared);
  }

  function testClearQueueItem() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testClearQueueItem', $mailchimp_list_id);
    $group = CRM_CiviMailchimp_Utils::getGroupById($mailchimp_sync_setting->civicrm_group_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_removed_from_group($group, $contact);
    $item = $queue->claimItem($lease_time = 0);
    $existing_mailchimp_sync_log = self::createTestLogMessage('This is a test error message', $details = NULL, $direction = 'civicrm_to_mailchimp', $type = 'error', $item->id);
    CRM_CiviMailchimp_BAO_SyncLog::clearQueueItem($item->id);
    $query = "
      SELECT
        *
      FROM
        civicrm_queue_item
      WHERE
        queue_name = 'mailchimp-sync'
      AND
        id = %1
      LIMIT 1
      ";
    $params = array(
      1 => array($item->id, 'Integer'),
    );
    $new_item = CRM_Core_DAO::executeQuery($query, $params, TRUE, 'CRM_Queue_DAO_QueueItem');
    $mailchimp_sync_log = CRM_CiviMailchimp_BAO_SyncLog::findById($existing_mailchimp_sync_log->id);
    $this->assertFalse($new_item->fetch());
    $this->assertNull($mailchimp_sync_log->civicrm_queue_item_id);
  }

  static function createTestLogMessage($message, $details = NULL, $direction = 'civicrm_to_mailchimp', $type = 'error', $civicrm_queue_item_id = NULL) {
    $mailchimp_sync_log = CRM_CiviMailchimp_BAO_SyncLog::saveMessage($type, $direction, $message, $details, $civicrm_queue_item_id);
    return $mailchimp_sync_log;
  }
}
