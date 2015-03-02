<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Tests for the CRM_CiviMailchimp_Utils class.
 */
class CRM_CiviMailchimp_UtilsTest extends CiviUnitTestCase {

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
    $this->quickCleanup(array('civicrm_email', 'civicrm_queue_item', 'civimailchimp_sync_settings', 'civimailchimp_interest_groups_sync_settings'));
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    parent::tearDown();
  }

  function testInitiateMailchimpApiCall() {
    $mailchimp = CRM_CiviMailchimp_Utils::initiateMailchimpApiCall();
    $this->assertObjectHasAttribute('lists', $mailchimp);
  }

  function testGetLists() {
    $lists = CRM_CiviMailchimp_Utils::getLists();
    $this->assertCount(3, $lists);

    $list_ids = array('MailchimpListsTestListA', 'MailchimpListsTestListB');
    $lists = CRM_CiviMailchimp_Utils::getLists($list_ids);
    $this->assertCount(2, $lists);

    $list_ids = array('InvalidList');
    $lists = CRM_CiviMailchimp_Utils::getLists($list_ids);
    $this->assertCount(0, $lists);
  }

  function testGetInterestGroups() {
    $list_id = 'MailchimpListsTestListA';
    $interest_groups = CRM_CiviMailchimp_Utils::getInterestGroups($list_id);
    $this->assertCount(3, $interest_groups['MailchimpTestInterestGroupingA']);
    $this->assertEquals('Test Interest Group A', $interest_groups['MailchimpTestInterestGroupingA']['MailchimpTestInterestGroupA']);
    $this->assertEquals('Test Interest Group B', $interest_groups['MailchimpTestInterestGroupingA']['MailchimpTestInterestGroupB']);
    $this->assertEquals('Test Interest Group C', $interest_groups['MailchimpTestInterestGroupingA']['MailchimpTestInterestGroupC']);
  }

  function testFormatListsAsSelectOptions() {
    $mailchimp_lists = CRM_CiviMailchimp_Utils::getLists();
    $list_options = CRM_CiviMailchimp_Utils::formatListsAsSelectOptions($mailchimp_lists);
    $this->assertCount(4, $list_options);
    $this->assertEquals('- select a list -', $list_options['']);
    $this->assertEquals('Test List A', $list_options['MailchimpListsTestListA']);
    $this->assertEquals('Test List B', $list_options['MailchimpListsTestListB']);
    $this->assertEquals('Test List C', $list_options['MailchimpListsTestListC']);
  }

  function testFormatInterestGroupsLookup() {
    $mailchimp_lists = CRM_CiviMailchimp_Utils::getLists();
    $interest_groups_lookup = CRM_CiviMailchimp_Utils::formatInterestGroupsLookup($mailchimp_lists);
    $this->assertCount(3, $interest_groups_lookup['MailchimpListsTestListA']);
    $this->assertEquals('Test Interest Group A', $interest_groups_lookup['MailchimpListsTestListA']['MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA']);
    $this->assertEquals('Test Interest Group B', $interest_groups_lookup['MailchimpListsTestListA']['MailchimpTestInterestGroupingA_MailchimpTestInterestGroupB']);
    $this->assertEquals('Test Interest Group C', $interest_groups_lookup['MailchimpListsTestListA']['MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC']);
    $this->assertArrayNotHasKey('MailchimpListsTestListB', $interest_groups_lookup);
    $this->assertArrayNotHasKey('MailchimpListsTestListC', $interest_groups_lookup);
  }

  function testGetMailchimpMergeFields() {
    // Test that default Merge Fields are accessible.
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $this->assertCount(2, $merge_fields);
    $this->assertEquals('first_name', $merge_fields['FNAME']);
    $this->assertEquals('last_name', $merge_fields['LNAME']);
    // Test that custom Merge Field settings are accessible.
    $custom_merge_fields_setting['MailchimpListsTestListA'] = array(
      'FIRSTNAME' => 'first_name',
      'LASTNAME' => 'last_name',
    );
    CRM_Core_BAO_Setting::setItem($custom_merge_fields_setting, 'CiviMailchimp Preferences', 'mailchimp_merge_fields');
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields('MailchimpListsTestListA');
    $this->assertCount(2, $merge_fields);
    $this->assertEquals('first_name', $merge_fields['FIRSTNAME']);
    $this->assertEquals('last_name', $merge_fields['LASTNAME']);
    // Remove the custom merge fields setting.
    $custom_merge_fields_setting = array();
    CRM_Core_BAO_Setting::setItem($custom_merge_fields_setting, 'CiviMailchimp Preferences', 'mailchimp_merge_fields');
  }

  function testFormatMailchimpMergeVars() {
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $contact_id = $this->individualCreate();
    $contact = CRM_CiviMailchimp_Utils::getContactById($contact_id);
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact);
    $this->assertCount(2, $merge_vars);
    $this->assertEquals($contact->first_name, $merge_vars['FNAME']);
    $this->assertEquals($contact->last_name, $merge_vars['LNAME']);
    $this->assertArrayNotHasKey('new-email', $merge_vars);
    $updated_mailchimp_email = 'foo@test.com';
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact, $updated_mailchimp_email);
    $this->assertCount(3, $merge_vars);
    $this->assertEquals($contact->first_name, $merge_vars['FNAME']);
    $this->assertEquals($contact->last_name, $merge_vars['LNAME']);
    $this->assertEquals($updated_mailchimp_email, $merge_vars['new-email']);
  }

  function testInterestGroupingsMergeVar() {
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testInterestGroupingsMergeVar', 'MailchimpListsTestListA', $mailchimp_interest_groups);
    $groupings_merge_var = CRM_CiviMailchimp_Utils::interestGroupingsMergeVar($mailchimp_sync_setting->mailchimp_list_id);
    $this->assertEquals('MailchimpTestInterestGroupingA', $groupings_merge_var[0]['id']);
    $this->assertEquals('Test Interest Group A', $groupings_merge_var[0]['groups'][0]);
    $this->assertEquals('Test Interest Group C', $groupings_merge_var[0]['groups'][1]);
  }

  function testCreateContactFromMailchimpRequest() {
    $request_data = CRM_CiviMailchimp_Page_WebhookTest::sampleRequestSubscribeOrProfileUpdate();
    $contact = CRM_CiviMailchimp_Utils::createContactFromMailchimpRequest($request_data);
    $location_type = CRM_Core_BAO_LocationType::getDefault();
    $this->assertEquals('Individual', $contact->contact_type);
    $this->assertEquals($request_data['merges']['FNAME'], $contact->first_name);
    $this->assertEquals($request_data['merges']['LNAME'], $contact->last_name);
    $this->assertEquals($request_data['email'], $contact->email[0]->email);
    $this->assertEquals(1, $contact->email[0]->is_primary);
    $this->assertEquals($location_type->id, $contact->email[0]->location_type_id);
  }

  function testUpdateContactFromMailchimpRequest() {
    $request_data = CRM_CiviMailchimp_Page_WebhookTest::sampleRequestSubscribeOrProfileUpdate();
    $contact = CRM_CiviMailchimp_Utils::createContactFromMailchimpRequest($request_data);
    $rand = rand();
    $request_data['merges']['FNAME'] = "CiviNew{$rand}";
    $request_data['merges']['LNAME'] = "MailchimpNew{$rand}";
    $updated_contact = CRM_CiviMailchimp_Utils::updateContactFromMailchimpRequest($request_data, $contact);
    $this->assertEquals($contact->id, $updated_contact->id);
    $this->assertEquals($request_data['merges']['FNAME'], $updated_contact->first_name);
    $this->assertEquals($request_data['merges']['LNAME'], $updated_contact->last_name);
  }

  function testDetermineMailchimpEmailForContact() {
    $params = array(
      'first_name' => 'MCFirst',
      'last_name' => 'MCLast',
      'contact_type' => 'Individual',
      'do_not_email' => TRUE,
    );
    $initial_contact = CRM_Contact_BAO_Contact::add($params);
    $primary_email = new CRM_Core_BAO_Email();
    $primary_email->contact_id = $initial_contact->id;
    $primary_email->email = 'mcfirst_mclast_primary@civicrm.org';
    $primary_email->is_primary = TRUE;
    $primary_email->save();
    $bulk_email = new CRM_Core_BAO_Email();
    $bulk_email->contact_id = $initial_contact->id;
    $bulk_email->email = 'mcfirst_mclast_bulk@civicrm.org';
    $bulk_email->is_bulkmail = TRUE;
    $bulk_email->save();
    $contact = CRM_CiviMailchimp_Utils::getContactById($initial_contact->id);
    // Test that a contact with do_not_mail does not return an email address.
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertNull($mailchimp_email);
    // Test that a contact with is_opt_out does not return an email address.
    $contact->do_not_email = FALSE;
    $contact->is_opt_out = TRUE;
    $contact->save();
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertNull($mailchimp_email);
    // Test that a is_bulkmail email is returned rather than an is_primary one.
    $contact->is_opt_out = FALSE;
    $contact->save();
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertEquals($bulk_email->email, $mailchimp_email);
    // Test that is_primary email is returned if no is_bulkmail email exists.
    $bulk_email->is_bulkmail = FALSE;
    $bulk_email->save();
    $contact = CRM_CiviMailchimp_Utils::getContactById($initial_contact->id);
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertEquals($primary_email->email, $mailchimp_email);
    // Test that is_primary email marked on_hold with no is_bulkmail email
    // does not return an email address.
    $primary_email->on_hold = TRUE;
    $primary_email->save();
    $contact = CRM_CiviMailchimp_Utils::getContactById($initial_contact->id);
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertNull($mailchimp_email);
  }

  function testContactAddedToGroup() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test group testContactAddedToGroup');
    // Test that the contact is not in the group (contactAddedToGroup returns TRUE)
    $contact_added_to_group = CRM_CiviMailchimp_Utils::contactAddedToGroup($mailchimp_sync_setting->civicrm_group_id, $contact->id);
    $this->assertTrue($contact_added_to_group);
    // Test that the contact is in the group (contactAddedToGroup returns FALSE)
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $contact_added_to_group = CRM_CiviMailchimp_Utils::contactAddedToGroup($mailchimp_sync_setting->civicrm_group_id, $contact->id);
    $this->assertFalse($contact_added_to_group);
    // Test that a status other than 'Added' returns TRUE
    CRM_Contact_BAO_GroupContact::removeContactsFromGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $contact_added_to_group = CRM_CiviMailchimp_Utils::contactAddedToGroup($mailchimp_sync_setting->civicrm_group_id, $contact->id);
    $this->assertTrue($contact_added_to_group);
  }

  function testGetContactByIdException() {
    $invalid_contact_id = '99999999999999999';
    $this->setExpectedException('CRM_Core_Exception', "Could not find Contact record with ID {$invalid_contact_id}");
    $returned_contact = CRM_CiviMailchimp_Utils::getContactById($invalid_contact_id);
    $this->assertEmpty($returned_contact);
  }

  function testGetContactById() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $initial_contact = CRM_Contact_BAO_Contact::create($params);
    // Test that we can return the matching contact DAO object
    $returned_contact = CRM_CiviMailchimp_Utils::getContactById($initial_contact->id);
    $this->assertEquals($initial_contact->id, $returned_contact->id);
    // Test that we can return the matching email DAO object
    $this->assertEquals($initial_contact->email[0]->email, $returned_contact->email[0]->email);
  }

  function testGetContactsWithPrimaryOrBulkEmailException() {
    $rand = rand();
    $email = "should_throw_exception{$rand}@exception.com";
    $this->setExpectedException('CRM_Core_Exception', "Could not find contact record with the email {$email}.");
    $contacts = CRM_CiviMailchimp_Utils::getContactsWithPrimaryOrBulkEmail($email, $throw_exception = TRUE);
    $this->assertEmpty($contacts);
  }

  function testGetContactsWithPrimaryOrBulkEmailNoException() {
    $rand = rand();
    $email = "should_throw_exception{$rand}@exception.com";
    $contacts = CRM_CiviMailchimp_Utils::getContactsWithPrimaryOrBulkEmail($email, $throw_exception = FALSE);
    $this->assertEmpty($contacts);
  }

  function testGetContactsWithPrimaryOrBulkEmail() {
    // Contact with one primary email.
    $primary_email_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $email = $primary_email_params['email'][0]['email'];
    $primary_email_contact = CRM_Contact_BAO_Contact::create($primary_email_params);
    // Contact with bulkmail email and primary email.
    $bulkmail_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $bulkmail_params['email'][0] = array(
      'email' => $email,
      'is_bulkmail' => 1,
      'is_primary' => 0,
    );
    $bulkmail_contact = CRM_Contact_BAO_Contact::create($bulkmail_params);
    // Contact set to Do Not Email.
    $do_not_email_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $do_not_email_params['do_not_email'] = 1;
    $do_not_email_contact = CRM_Contact_BAO_Contact::create($do_not_email_params);
    // Contact set to Opt Out of Bulk Emails.
    $is_opt_out_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $is_opt_out_params['is_opt_out'] = 1;
    $is_opt_out_contact = CRM_Contact_BAO_Contact::create($is_opt_out_params);
    // Contact with all emails On Hold.
    $on_hold_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $on_hold_params['email'][0]['on_hold'] = 1;
    $on_hold_contact = CRM_Contact_BAO_Contact::create($on_hold_params);
    $contacts = CRM_CiviMailchimp_Utils::getContactsWithPrimaryOrBulkEmail($email, $throw_exception = TRUE);
    $this->assertCount(2, $contacts);
    // Test the first contact.
    $this->assertEquals($email, $contacts[0]->email[0]->email);
    $this->assertEquals(1, $contacts[0]->email[0]->is_primary);
    $this->assertEquals(0, $contacts[0]->email[0]->on_hold);
    $this->assertEquals(0, $contacts[0]->do_not_email);
    $this->assertEquals(0, $contacts[0]->is_opt_out);
    // Test the second contact.
    $this->assertEquals($email, $contacts[1]->email[0]->email);
    $this->assertEquals(1, $contacts[1]->email[0]->is_bulkmail);
    $this->assertEquals(0, $contacts[1]->email[0]->on_hold);
    $this->assertEquals(0, $contacts[1]->do_not_email);
    $this->assertEquals(0, $contacts[1]->is_opt_out);
  }

  function testGetContactInMailchimpListByEmailException() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $email = $params['email'][0]['email'];
    $contact = CRM_Contact_BAO_Contact::create($params);
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test group testGetContactInMailchimpListByEmailException');
    $this->setExpectedException('CRM_Core_Exception', "Contact record with email {$email} not found in group ID {$mailchimp_sync_setting->civicrm_group_id}.");
    $mailchimp_contact = CRM_CiviMailchimp_Utils::getContactInMailchimpListByEmail($email, $mailchimp_sync_setting->mailchimp_list_id);
  }

  function testGetContactInMailchimpListByEmail() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $email = $params['email'][0]['email'];
    $contact = CRM_Contact_BAO_Contact::create($params);
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test group testGetContactInMailchimpListByEmail');
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $mailchimp_contact = CRM_CiviMailchimp_Utils::getContactInMailchimpListByEmail($email, $mailchimp_sync_setting->mailchimp_list_id);
    $this->assertEquals($contact->id, $mailchimp_contact->id);
    $this->assertEquals($email, $mailchimp_contact->email[0]->email);
  }

  function testGetEmailbyIdException() {
    $invalid_email_id = '99999999999999999';
    $this->setExpectedException('CRM_Core_Exception', "Could not find Email record with ID {$invalid_email_id}");
    CRM_CiviMailchimp_Utils::getEmailbyId($invalid_email_id);
  }

  function testGetEmailbyId() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $email = $params['email'][0]['email'];
    $contact = CRM_Contact_BAO_Contact::create($params);
    $contact_email_id = $contact->email[0]->id;
    $returned_email = CRM_CiviMailchimp_Utils::getEmailbyId($contact_email_id);
    $this->assertEquals($email, $returned_email->email); 
  }

  function testGetGroupByIdException() {
    $invalid_group_id = '99999999999999999';
    $this->setExpectedException('CRM_Core_Exception', "Could not find Group record with ID {$invalid_group_id}");
    CRM_CiviMailchimp_Utils::getGroupById($invalid_group_id);
  }

  function testGetGroupById() {
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test group testGetGroupById'); 
    $civicrm_group_id = $mailchimp_sync_setting->civicrm_group_id;
    $returned_group = CRM_CiviMailchimp_Utils::getGroupById($civicrm_group_id);
    $this->assertEquals('Test group testGetGroupById', $returned_group->name);
  }

  function testAddContactToGroup() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test group testAddContactToGroup');
    // Test that the contact is not in the group
    $contact_added_to_group = CRM_Contact_BAO_GroupContact::isContactInGroup($contact->id, $mailchimp_sync_setting->civicrm_group_id);
    $this->assertFalse($contact_added_to_group);
    // Test that the contact is in the group
    CRM_CiviMailchimp_Utils::addContactToGroup($contact, $mailchimp_sync_setting->mailchimp_list_id);
    $contact_added_to_group = CRM_Contact_BAO_GroupContact::isContactInGroup($contact->id, $mailchimp_sync_setting->civicrm_group_id);
    $this->assertTrue($contact_added_to_group);
  }

  function testRemoveContactFromGroup() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test group testRemoveContactFromGroup');
    // Test that the contact is in the group
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $contact_added_to_group = CRM_Contact_BAO_GroupContact::isContactInGroup($contact->id, $mailchimp_sync_setting->civicrm_group_id);
    $this->assertTrue($contact_added_to_group);
    // Test that the contact is removed from the group
    CRM_CiviMailchimp_Utils::removeContactFromGroup($contact, $mailchimp_sync_setting->mailchimp_list_id);
    $contact_added_to_group = CRM_Contact_BAO_GroupContact::isContactInGroup($contact->id, $mailchimp_sync_setting->civicrm_group_id);
    $this->assertFalse($contact_added_to_group);
  }

  function testAddWebhookToMailchimpList() {
    $result = CRM_CiviMailchimp_Utils::addWebhookToMailchimpList('MailchimpListsTestListA');
    $this->assertEquals('MailchimpTestWebhookA', $result['id']);
  }

  function testAddWebhookToMailchimpListInvalidList() {
    $result = CRM_CiviMailchimp_Utils::addWebhookToMailchimpList('MailchimpListsTestListB');
    $this->assertEquals('List_DoesNotExist', $result['name']);
  }

  function testDeleteWebhookFromMailchimpList() {
    $result = CRM_CiviMailchimp_Utils::deleteWebhookFromMailchimpList('MailchimpListsTestListA');
    $this->assertTrue($result['complete']);
  }

  function testDeleteWebhookFromMailchimpListInvalidList() {
    $result = CRM_CiviMailchimp_Utils::deleteWebhookFromMailchimpList('MailchimpListsTestListB');
    $this->assertEquals('List_DoesNotExist', $result['name']);
  }

  function testFormatMailchimpWebhookUrl() {
    $url = CIVICRM_UF_BASEURL;
    $url = CRM_Utils_File::addTrailingSlash($url);
    $site_key = CRM_CiviMailchimp_Utils::getSiteKey();
    $expected_webhook_url = "{$url}civicrm/mailchimp/webhook?key={$site_key}";
    $webhook_url = CRM_CiviMailchimp_Utils::formatMailchimpWebhookUrl();
    $this->assertEquals($expected_webhook_url, $webhook_url);
  }

  function testFormatMailchimpWebhookUrlCustom() {
    $url = 'http://example.org/';
    $base_url = CRM_Core_BAO_Setting::setItem($url, 'CiviMailchimp Preferences', 'mailchimp_webhook_base_url');
    $site_key = CRM_CiviMailchimp_Utils::getSiteKey();
    $expected_webhook_url = "{$url}civicrm/mailchimp/webhook?key={$site_key}";
    $webhook_url = CRM_CiviMailchimp_Utils::formatMailchimpWebhookUrl();
    $this->assertEquals($expected_webhook_url, $webhook_url);
    $base_url = CRM_Core_BAO_Setting::setItem('', 'CiviMailchimp Preferences', 'mailchimp_webhook_base_url');
  }

  function testGetSiteKey() {
    $site_key = CRM_CiviMailchimp_Utils::getSiteKey();
    $this->assertEquals(CIVICRM_SITE_KEY, $site_key);
  }

  function testAddMailchimpSyncQueueItem() {
    $action = 'subscribeContactToMailchimpList';
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    CRM_CiviMailchimp_Utils::addMailchimpSyncQueueItem($action, $mailchimp_list_id, $params['email'][0]['email'], $merge_vars);
    $item = $queue->claimItem();
    $this->assertEquals('CRM_CiviMailchimp_Utils', $item->data->callback[0]);
    $this->assertEquals('processCiviMailchimpQueueItem', $item->data->callback[1]);
    $this->assertEquals($action, $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($params['email'][0]['email'], $item->data->arguments[2]);
    $this->assertEquals($merge_vars, $item->data->arguments[3]);
  }

  function testSubscribeContactToMailchimpList() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testSubscribeContactToMailchimpList', $mailchimp_list_id, $mailchimp_interest_groups);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $email = $params['email'][0]['email'];
    $merge_vars = array();
    $response = CRM_CiviMailchimp_Utils::subscribeContactToMailchimpList($mailchimp_list_id, $email, $merge_vars);
    $this->assertEquals($email, $response['email']);
  }

  function testUnsubscribeContactFromMailchimpList() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $email = $params['email'][0]['email'];
    $response = CRM_CiviMailchimp_Utils::unsubscribeContactFromMailchimpList($mailchimp_list_id, $email);
    $this->assertTrue($response['complete']);
  }

  function testUpdateContactProfileInMailchimp() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testUpdateContactProfileInMailchimp', $mailchimp_list_id, $mailchimp_interest_groups);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $email = $params['email'][0]['email'];
    $merge_vars = array();
    $response = CRM_CiviMailchimp_Utils::updateContactProfileInMailchimp($mailchimp_list_id, $email, $merge_vars);
    $this->assertEquals($email, $response['email']);
  }

  function testRetrieveMailchimpMemberExportFile() {
    $url = __DIR__ . '/../../sample_mailchimp_export.txt';
    $url = 'file:///' . realpath($url);
    $list_id = 'MailchimpListsTestListA';
    $file_path = CRM_CiviMailchimp_Utils::retrieveMailchimpMemberExportFile($url, $list_id);
    $this->assertFileExists($file_path);
    $this->assertFileEquals($url, $file_path);
    CRM_CiviMailchimp_Utils::deleteMailchimpMemberExportFile($file_path);
    $this->assertFileNotExists($file_path);
  }

  function testExtractMembersFromMailchimpExportFile() {
    $file_path = __DIR__ . '/../../sample_mailchimp_export.txt';
    $file_path = realpath($file_path);
    $list_id = 'MailchimpListsTestListA';
    $members = CRM_CiviMailchimp_Utils::extractMembersFromMailchimpExportFile($file_path, $list_id);
    $this->assertCount(3, $members);
    $this->assertEquals('civimailchimp+test1@civimailchimp.org', $members[0]['email']);
    $this->assertEquals($list_id, $members[0]['list_id']);
    $this->assertEquals('Civi1', $members[0]['merges']['FNAME']);
    $this->assertEquals('Mailchimp1', $members[0]['merges']['LNAME']);
    $this->assertEquals('civimailchimp+test2@civimailchimp.org', $members[1]['email']);
    $this->assertEquals($list_id, $members[1]['list_id']);
    $this->assertEquals('Civi2', $members[1]['merges']['FNAME']);
    $this->assertEquals('Mailchimp2', $members[1]['merges']['LNAME']);
    $this->assertEquals('civimailchimp+test3@civimailchimp.org', $members[2]['email']);
    $this->assertEquals($list_id, $members[2]['list_id']);
    $this->assertEquals('Civi3', $members[2]['merges']['FNAME']);
    $this->assertEquals('Mailchimp3', $members[2]['merges']['LNAME']);
  }

  function testCreateSyncScheduledJob() {
    $expected_job = CRM_CiviMailchimp_Utils::createSyncScheduledJob();
    $job = new CRM_Core_BAO_Job();
    $job->name = 'Sync Contacts to Mailchimp';
    $job->find(TRUE);
    $this->assertEquals($expected_job->id, $job->id);
    $this->assertEquals($expected_job->name, $job->name);
  }

  function test_civicrm_api3_civi_mailchimp_sync() {
    $action = 'subscribeContactToMailchimpList';
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civicrm_api3_civi_mailchimp_sync', $mailchimp_list_id, $mailchimp_interest_groups);
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact);
    CRM_CiviMailchimp_Utils::addMailchimpSyncQueueItem($action, $mailchimp_list_id, $params['email'][0]['email'], $merge_vars);
    $action = 'unsubscribeContactFromMailchimpList';
    CRM_CiviMailchimp_Utils::addMailchimpSyncQueueItem($action, $mailchimp_list_id, $params['email'][0]['email'], $merge_vars);
    $job_params['records_to_process_per_run'] = 100;
    civicrm_api3_civi_mailchimp_sync($job_params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => FALSE,
    ));
    $this->assertEquals(0, $queue->numberOfItems());
  }

  static function sampleContactParams() {
    $rand = rand();
    $params = array(
      'first_name' => "Civi{$rand}",
      'last_name' => "Mailchimp{$rand}",
      'contact_type' => 'Individual',
      'email' => array(
        array(
          'email' => "civimailchimp_test+{$rand}@civimailchimp.org",
          'is_primary' => 1,
        ),
      ),
    );

    return $params;
  }
}
