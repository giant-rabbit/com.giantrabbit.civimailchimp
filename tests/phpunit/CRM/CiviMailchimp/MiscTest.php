<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * This test class includes any functions not part of a class.
 */
class CRM_CiviMailchimp_MiscTest extends CiviUnitTestCase {

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
    $this->quickCleanup(array('civicrm_email', 'civicrm_queue_item'));
    parent::tearDown();
  }

  function test_civimailchimp_civicrm_contact_added_to_group() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_contact_added_to_group', $mailchimp_list_id);
    $group = CRM_CiviMailchimp_Utils::getGroupById($mailchimp_sync_setting->civicrm_group_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_added_to_group($group, $contact);
    $item = $queue->claimItem();
    $this->assertEquals('subscribeContactToMailchimpList', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($params['email'][0]['email'], $item->data->arguments[2]);
    $this->assertEquals($params['first_name'], $item->data->arguments[3]['FNAME']);
    $this->assertEquals($params['last_name'], $item->data->arguments[3]['LNAME']);
  }

  function test_civimailchimp_civicrm_contact_added_to_group_no_sync_settings() {
    $group_name = 'Test Group test_contact_added_to_group_no_sync_settings';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $group = CRM_CiviMailchimp_Utils::getGroupById($group_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_added_to_group($group, $contact);
    $this->assertEquals(0, $queue->numberOfItems());
  }

  function test_civimailchimp_civicrm_contact_removed_from_group() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_contact_removed_from_group', $mailchimp_list_id);
    $group = CRM_CiviMailchimp_Utils::getGroupById($mailchimp_sync_setting->civicrm_group_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_removed_from_group($group, $contact);
    $item = $queue->claimItem();
    $this->assertEquals('unsubscribeContactFromMailchimpList', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($params['email'][0]['email'], $item->data->arguments[2]);
  }

  function test_civimailchimp_civicrm_contact_removed_from_group_no_sync_settings() {
    $group_name = 'Test Group test_contact_removed_from_group_no_sync_settings';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $group = CRM_CiviMailchimp_Utils::getGroupById($group_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql', 
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_removed_from_group($group, $contact);
    $this->assertEquals(0, $queue->numberOfItems());
  }

  function test_civicrm_api3_civi_mailchimp_sync_exception() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civicrm_api3_civi_mailchimp_sync_exception', $mailchimp_list_id, $mailchimp_interest_groups);
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact);
    CRM_CiviMailchimp_Utils::addMailchimpSyncQueueItem('subscribeContactToMailchimpList', 'MailchimpListsTestListB', $params['email'][0]['email'], $merge_vars);
    $action = 'unsubscribeContactFromMailchimpList';
    CRM_CiviMailchimp_Utils::addMailchimpSyncQueueItem('unsubscribeContactFromMailchimpList', 'MailchimpListsTestListB', $params['email'][0]['email']);
    $job_params['records_to_process_per_run'] = 100;
    civicrm_api3_civi_mailchimp_sync($job_params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => FALSE,
    ));
    $this->assertEquals(2, $queue->numberOfItems());
  }
}
