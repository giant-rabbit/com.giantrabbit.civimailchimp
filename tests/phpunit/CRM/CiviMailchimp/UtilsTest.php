<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Tests for the CRM_CiviMailchimp_Utils class.
 */
class CRM_CiviMailchimp_UtilsTest extends CiviUnitTestCase {
  function setUp() {
    $this->quickCleanup(array('civicrm_contact', 'civicrm_email'));
    // Use the Mailchimp API test class for all the tests.
    CRM_Core_BAO_Setting::setItem('CRM_CiviMailchimpTest', 'CiviMailchimp Preferences', 'mailchimp_api_class');
    parent::setUp();
  }

  function tearDown() {
    parent::tearDown();
  }

  function testInitiateMailchimpApiCall() {
    $mailchimp = CRM_CiviMailchimp_Utils::initiateMailchimpApiCall();
    $this->assertObjectHasAttribute('lists', $mailchimp);
  }

  function testGetInterestGroups() {
    $list_id = 'MailchimpListsTestListA';
    $interest_groups = CRM_CiviMailchimp_Utils::getInterestGroups($list_id);
    $this->assertCount(3, $interest_groups['MailchimpTestInterestGroupingA']);
    $this->assertArrayHasKey('MailchimpTestInterestGroupingA', $interest_groups);
  }

  function testFormatListsAsSelectOptions() {
    $mailchimp_lists = CRM_CiviMailchimp_Utils::getLists();
    $list_options = CRM_CiviMailchimp_Utils::formatListsAsSelectOptions($mailchimp_lists);
    $this->assertArrayHasKey('', $list_options);
    $this->assertArrayHasKey('MailchimpListsTestListA', $list_options);
    $this->assertArrayHasKey('MailchimpListsTestListB', $list_options);
    $this->assertArrayHasKey('MailchimpListsTestListC', $list_options);
  }

  function testFormatInterestGroupsLookup() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  function testGetMailchimpMergeFields() {
    // Test that default Merge Fields are accessible.
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $this->assertArrayHasKey('FNAME', $merge_fields);
    $this->assertArrayHasKey('LNAME', $merge_fields);
    // Test that custom Merge Field settings are accessible.
    $custom_merge_fields_setting['MailchimpListsTestListA'] = array(
      'FIRSTNAME' => 'first_name',
      'LASTNAME' => 'last_name',
    );
    CRM_Core_BAO_Setting::setItem($custom_merge_fields_setting, 'CiviMailchimp Preferences', 'mailchimp_merge_fields');
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields('MailchimpListsTestListA');
    $this->assertArrayHasKey('FIRSTNAME', $merge_fields);
    $this->assertArrayHasKey('LASTNAME', $merge_fields);
  }

  function testFormatMailchimpMergeVars() {
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $contact_id = $this->individualCreate();
    $contact = CRM_CiviMailchimp_Utils::getContactById($contact_id);
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact);
    $this->assertEquals($merge_vars['FNAME'], $contact->first_name);
    $this->assertEquals($merge_vars['LNAME'], $contact->last_name);
    $this->assertArrayNotHasKey('new-email', $merge_vars);
    $updated_mailchimp_email = 'foo@test.com';
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact, $updated_mailchimp_email);
    $this->assertEquals($merge_vars['FNAME'], $contact->first_name);
    $this->assertEquals($merge_vars['LNAME'], $contact->last_name);
    $this->assertEquals($merge_vars['new-email'], $updated_mailchimp_email);
  }

  function testInterestGroupingsMergeVar() {
    $group_id = $this->groupCreate(array('name' => 'Test Group interestGroupingsMergeVar'));
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestSettings($group_id, $mailchimp_list_id, $mailchimp_interest_groups);
    $groupings_merge_var = CRM_CiviMailchimp_Utils::interestGroupingsMergeVar($mailchimp_list_id);
    $this->assertEquals($groupings_merge_var[0]['id'], 'MailchimpTestInterestGroupingA');
    $this->assertEquals($groupings_merge_var[0]['groups'][0], 'Test Interest Group A');
    $this->assertEquals($groupings_merge_var[0]['groups'][1], 'Test Interest Group C');
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
    $this->assertEquals($mailchimp_email, $bulk_email->email);
    // Test that is_primary email is returned if no is_bulkmail email exists.
    $bulk_email->is_bulkmail = FALSE;
    $bulk_email->save();
    $contact = CRM_CiviMailchimp_Utils::getContactById($initial_contact->id);
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertEquals($mailchimp_email, $primary_email->email);
    // Test that is_primary email marked on_hold with no is_bulkmail email 
    // does not return an email address.
    $primary_email->on_hold = TRUE;
    $primary_email->save();
    $contact = CRM_CiviMailchimp_Utils::getContactById($initial_contact->id);
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertNull($mailchimp_email);
  }
}
