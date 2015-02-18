<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Tests for the CRM_CiviMailchimp_Utils class.
 */
class CRM_CiviMailchimp_UtilsTest extends CiviUnitTestCase {

  static function setUpBeforeClass() {
    // Use the Mailchimp API test class for all the tests.
    CRM_Core_BAO_Setting::setItem('CRM_CiviMailchimpTest', 'CiviMailchimp Preferences', 'mailchimp_api_class');
  }

  function setUp() {
    $this->quickCleanup(array('civicrm_contact', 'civicrm_email'));
    parent::setUp();
  }

  function tearDown() {
    parent::tearDown();
  }

  function testInitiateMailchimpApiCall() {
    $mailchimp = CRM_CiviMailchimp_Utils::initiateMailchimpApiCall();
    $this->assertObjectHasAttribute('lists', $mailchimp);
  }

  function testGetLists() {
    $lists = CRM_CiviMailchimp_Utils::getLists();
    $this->assertCount(3, $lists);

    $list_ids = array('mailchimp_lists_test_list_a', 'mailchimp_lists_test_list_b');
    $lists = CRM_CiviMailchimp_Utils::getLists($list_ids);
    $this->assertCount(2, $lists);

    $list_ids = array('invalid_list_id');
    $lists = CRM_CiviMailchimp_Utils::getLists($list_ids);
    $this->assertCount(0, $lists);
  }

  function testGetInterestGroups() {
    $list_id = 'MailchimpListsTestListA';
    $interest_groups = CRM_CiviMailchimp_Utils::getInterestGroups($list_id);
    $this->assertCount(3, $interest_groups['MailchimpTestInterestGroupingA']);
    $this->assertEquals($interest_groups['MailchimpTestInterestGroupingA']['MailchimpTestInterestGroupA'], 'Test Interest Group A');
    $this->assertEquals($interest_groups['MailchimpTestInterestGroupingA']['MailchimpTestInterestGroupB'], 'Test Interest Group B');
    $this->assertEquals($interest_groups['MailchimpTestInterestGroupingA']['MailchimpTestInterestGroupC'], 'Test Interest Group C');
  }

  function testFormatListsAsSelectOptions() {
    $mailchimp_lists = CRM_CiviMailchimp_Utils::getLists();
    $list_options = CRM_CiviMailchimp_Utils::formatListsAsSelectOptions($mailchimp_lists);
    $this->assertCount(4, $list_options);
    $this->assertEquals($list_options[''], '- select a list -');
    $this->assertEquals($list_options['MailchimpListsTestListA'], 'Test List A');
    $this->assertEquals($list_options['MailchimpListsTestListB'], 'Test List B');
    $this->assertEquals($list_options['MailchimpListsTestListC'], 'Test List C');
  }

  function testFormatInterestGroupsLookup() {
    $mailchimp_lists = CRM_CiviMailchimp_Utils::getLists();
    $interest_groups_lookup = CRM_CiviMailchimp_Utils::formatInterestGroupsLookup($mailchimp_lists);
    $this->assertCount(3, $interest_groups_lookup['MailchimpListsTestListA']);
    $this->assertEquals($interest_groups_lookup['MailchimpListsTestListA']['MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA'], 'Test Interest Group A');
    $this->assertEquals($interest_groups_lookup['MailchimpListsTestListA']['MailchimpTestInterestGroupingA_MailchimpTestInterestGroupB'], 'Test Interest Group B');
    $this->assertEquals($interest_groups_lookup['MailchimpListsTestListA']['MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC'], 'Test Interest Group C');
    $this->assertArrayNotHasKey('MailchimpListsTestListB', $interest_groups_lookup);
    $this->assertArrayNotHasKey('MailchimpListsTestListC', $interest_groups_lookup);
  }

  function testGetMailchimpMergeFields() {
    // Test that default Merge Fields are accessible.
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $this->assertCount(2, $merge_fields);
    $this->assertEquals($merge_fields['FNAME'], 'first_name');
    $this->assertEquals($merge_fields['LNAME'], 'last_name');
    // Test that custom Merge Field settings are accessible.
    $custom_merge_fields_setting['MailchimpListsTestListA'] = array(
      'FIRSTNAME' => 'first_name',
      'LASTNAME' => 'last_name',
    );
    CRM_Core_BAO_Setting::setItem($custom_merge_fields_setting, 'CiviMailchimp Preferences', 'mailchimp_merge_fields');
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields('MailchimpListsTestListA');
    $this->assertCount(2, $merge_fields);
    $this->assertEquals($merge_fields['FIRSTNAME'], 'first_name');
    $this->assertEquals($merge_fields['LASTNAME'], 'last_name');
  }

  function testFormatMailchimpMergeVars() {
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $contact_id = $this->individualCreate();
    $contact = CRM_CiviMailchimp_Utils::getContactById($contact_id);
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact);
    $this->assertCount(2, $merge_vars);
    $this->assertEquals($merge_vars['FNAME'], $contact->first_name);
    $this->assertEquals($merge_vars['LNAME'], $contact->last_name);
    $this->assertArrayNotHasKey('new-email', $merge_vars);
    $updated_mailchimp_email = 'foo@test.com';
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact, $updated_mailchimp_email);
    $this->assertCount(3, $merge_vars);
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
