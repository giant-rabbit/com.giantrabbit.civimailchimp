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
