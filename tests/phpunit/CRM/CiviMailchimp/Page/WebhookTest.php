<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Tests for Mailchimp Webhooks.
 */
class CRM_CiviMailchimp_Page_WebhookTest extends CiviUnitTestCase {

  static function setUpBeforeClass() {
    // Use the Mailchimp API test class for all the tests.
    CRM_Core_BAO_Setting::setItem('CRM_MailchimpMock', 'CiviMailchimp Preferences', 'mailchimp_api_class');
  }

  function setUp() {
    // If your test manipulates any SQL tables, then you should truncate
    // them to ensure a consisting starting point for all tests
    // $this->quickCleanup(array('example_table_name'));
    parent::setUp();
  }

  function tearDown() {
    parent::tearDown();
  }

  function testMailchimpWebhookSubscribe() {
    $sync_settings = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('test_group_mailchimp_webhook_subscribe');
    $sample_data = self::sampleRequestSubscribeOrProfileUpdate();
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookSubscribe($sample_data);
    $new_contact = CRM_Contact_BAO_Contact::matchContactOnEmail($sample_data['email']);
    $this->assertTrue(CRM_Contact_BAO_GroupContact::isContactInGroup($new_contact->contact_id, $sync_settings->civicrm_group_id));

    $sample_data = self::sampleRequestSubscribeOrProfileUpdate();
    $initial_contact = self::addContactFromSampleData($sample_data);
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookSubscribe($sample_data);
    $this->assertTrue(CRM_Contact_BAO_GroupContact::isContactInGroup($initial_contact->id, $sync_settings->civicrm_group_id));
  }

  function testMailchimpWebhookUnsubscribe() {
    $sync_settings = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('test_group_mailchimp_webhook_unsubscribe');
    $sample_data = self::sampleRequestSubscribeOrProfileUpdate();
    $contact = self::addContactFromSampleData($sample_data);
    CRM_Contact_BAO_GroupContact::addContactsToGroup(array($contact->id), $sync_settings->civicrm_group_id);
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookUnsubscribe($sample_data);
    $this->assertFalse(CRM_Contact_BAO_GroupContact::isContactInGroup($contact->id, $sync_settings->civicrm_group_id));
  }

  function testMailchimpWebhookUpemail() {
    $sample_contact_data = self::sampleRequestSubscribeOrProfileUpdate();
    $sample_upemail_data = self::sampleRequestUpemail();
    $sample_contact_data['email'] = $sample_upemail_data['old_email'];
    $sample_contact_data['merges']['EMAIL'] = $sample_upemail_data['old_email'];
    $contact = self::addContactFromSampleData($sample_contact_data);

    $sync_settings = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('test_group_mailchimp_webhook_upemail');
    CRM_Contact_BAO_GroupContact::addContactsToGroup(array($contact->id), $sync_settings->civicrm_group_id);
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookUpemail($sample_upemail_data);

    $contact_details = CRM_Contact_BAO_Contact::getContactDetails($contact->id);
    $this->assertEquals($sample_upemail_data['new_email'], $contact_details[1]);
  }

  function testMailchimpWebhookProfile() {
    $sample_data = self::sampleRequestSubscribeOrProfileUpdate();
    $contact = self::addContactFromSampleData($sample_data);
    $sync_settings = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('test_group_mailchimp_webhook_profile');
    CRM_Contact_BAO_GroupContact::addContactsToGroup(array($contact->id), $sync_settings->civicrm_group_id);

    $sample_new_data = self::sampleRequestSubscribeOrProfileUpdate();
    $sample_data['merges']['FNAME'] = $sample_new_data['merges']['FNAME'];
    $sample_data['merges']['LNAME'] = $sample_new_data['merges']['LNAME'];
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookProfile($sample_data);

    $updated_contact = new CRM_Contact_BAO_Contact();
    $updated_contact->id = $contact->id;
    $updated_contact->find(TRUE);
    $this->assertEquals($updated_contact->first_name, $sample_new_data['merges']['FNAME']);
    $this->assertEquals($updated_contact->last_name, $sample_new_data['merges']['LNAME']);
  }

  static function addContactFromSampleData($sample_data) {
    $params = array(
      'first_name' => $sample_data['merges']['FNAME'],
      'last_name' => $sample_data['merges']['LNAME'],
      'contact_type' => 'Individual',
      'do_not_email' => FALSE,
    );
    $contact = CRM_Contact_BAO_Contact::add($params);
    $primary_email = new CRM_Core_BAO_Email();
    $primary_email->contact_id = $contact->id;
    $primary_email->email = $sample_data['email'];
    $primary_email->is_primary = TRUE;
    $primary_email->save();
    return $contact;
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
