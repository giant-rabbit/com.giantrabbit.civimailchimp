CiviMailchimp Sync
==================

The CiviMailchimp extension allows CiviCRM Groups to sync to Mailchimp Lists. If a Group is configured to sync to a Mailchimp List any Contacts in that Group will be synced to Mailchimp. 

## What Makes It Different

There are a couple of other extensions that handle syncing with Mailchimp, but we decided to start from scratch with this one for a couple of reasons.

Tests! We determined that the best way to ensure that the extension was working was to have as much of the code covered by automated tests as possible, which we've done.

Logging and Error Handling! Sometimes things do go wrong, and when they do, we decided it's best to have them fail spectacularly. Throughout the code, we've added error handling and logging that surfaces errors to an admin accessible CiviMailchimp sync log as soon as a problem is detected so it can be taken care of quickly. In addition, popup error messages will be shown to the admin and will continue to be shown until an admin chooses to not display the message again. The worst thing is to find out months later that contacts were not being synced properly and having to unravel that mess! Finally, when CiviCRM's Debug mode is enabled, all incoming and outgoing requests from/to Mailchimp are logged in the CiviCRM log.

## How To Install and Configure

Follow the standard steps for installing and enabling an extension at http://wiki.civicrm.org/confluence/display/CRMDOC/Extensions.

Once the extension is enabled:

Enter your Mailchimp API Key by going to Administer > CiviMailchimp > Mailchimp Settings. If you need to use a different base url for Mailchimp's Webhooks than your CiviCRM base url, as you might if your site is behind an .htaccess password, you may enter it here, too. 

Both of these values can be overriden in your civicrm.settings.php file by following the procedures in http://wiki.civicrm.org/confluence/display/CRMDOC/Override+CiviCRM+Settings. The two values you can override are $civicrm_setting['CiviMailchimp Preferences']['mailchimp_api_key'] and $civicrm_setting['CiviMailchimp Preferences']['mailchimp_webhook_base_url'].

At this point, you should be able to edit a CiviCRM Group and associate it to a Mailchimp List (of course, you should setup at least one List in Mailchimp first). When you edit the Group, you'll see a new "Mailchimp Sync Settings" section where you can select the Mailchimp List the Group should sync with. If you've configured Interest Groups in Mailchimp for this List, you can also select some or all of those, too. Note that only one Mailchimp List can be associated with a CiviCRM Group at the same time. Also, to ensure that the First Name and Last Name fields get synced properly, the Mailchimp merge fields should be name FNAME and LNAME respectively.

Changes to Contacts in that Group are added to a CiviCRM Queue. In order to trigger the processing of that Queue, you must configure CiviCRM's Cron as documented at http://wiki.civicrm.org/confluence/display/CRMDOC/Managing+Scheduled+Jobs. Then, enable the "Sync Contacts to Mailchimp" Scheduled Job by going to Administer > System Settings > Scheduled Jobs. You can also manually execute the scheduled job here for debugging and testing purposes.

If the Group or Mailchimp List have existing records, in order to ensure those records are properly synced, you can run a Force Sync. Go to Administer > CiviMailchimp > Force Sync, select the CiviCRM Group and click Submit. This will perform a one-time sync between CiviCRM and Mailchimp.

## Important Architectural Choices

At their cores, CiviCRM and Mailchimp have very different architectural paradigms that make them somewhat tricky to keep records in Sync. In general, in order to keep things running smoothly, it is recommended that either CiviCRM or Mailchimp be designated the primary source and that updates to records should be performed in one only and then automatically synced back to the other. Doing this will limit the chances for data going out of sync, requiring admin intervention to correct.

Here are some of the major architectural choices made to mitigate issues with syncing.

#### Which Email Address To Sync

Mailchimp expects each record to have a single email address. CiviCRM allows Contacts to have multiple email addresses. So, if we're syncing a Contact record to Mailchimp and that Contact has multiple email addresses, which email address should we send to Mailchimp?

The first thing we check is that the Contact is not marked Do Not Email. If they are, then they don't get synced with Mailchimp. If they aren't, then we check to see if they have an email that is marked Bulk Mailings and is not On Hold. If they do, that's the email address we sync. If not, if they have a Primary email address that is not On Hold, then we use that email address. If they don't have that, then we don't sync the Contact to Mailchimp.

#### What About Multiple Contacts With the Same Email

Mailchimp expects that email addresses should be unique while CiviCRM allows you to have multiple Contacts with identical email addresses. That means we have to make a choice on how to handle Contacts with duplicate email addresses.

For example, if two contacts' primary email is "test@test.com", if that email is subscribed to a Mailchimp list, only one contact will be added to the Group set to sync with that list, since Mailchimp does not allow duplicate email addresses. In general, it is recommended that all but one of the duplicate emails be marked as On Hold or have the type changed from being the Primary or Bulk Mailings email address. If duplicates are found, a message will be noted in CiviCRM's log.

## Troubleshooting

There are a number of scenarios where syncing may cease to operate. Refer to
the CiviMailchimp Sync Log (Administer > CiviMailchimp > Sync Log) and the CiviCRM log to see any errors reported. Some of these messages may also appear in the Job Log for the "Sync Contacts to Mailchimp" Scheduled Job. In addition, an admin popup will appear with any sync errors. These notifications will continue to show until an admin selects not to show a particular notification again. Hopefully all of the notifications and logging will make determining and fixing sync problems easier.

More common errors include: 

#### Mailchimp Interest Group Renamed/Missing

If a Mailchimp Interest Group was renamed or removed, if a CiviCRM Group is configured to sync with the renamed Interest Group, syncing from CiviCRM to Mailchimp will fail with an "Mailchimp_List_InvalidInterestGroup" error along with a message stating which Interest Group is invalid.

To correct this issue, edit the CiviCRM Group set to sync with this Interest Group and select the new Interest Group and save. If the Interest Group was removed, just save the CiviCRM Group. Syncing should now continue properly.

#### Email Address Changed Both in Mailchimp and CiviCRM

There is the possibility that a contact's data in CiviCRM and Mailchimp can get out-of-sync and need to be manually corrected. One scenario where this may occur is if a Contact's email address is changed in CiviCRM and the scheduled job has not run yet. If, in that time, that same Contact's email is changed in Mailchimp, it will change the email address immediately in CiviCRM. However, once the scheduled job runs, the email address change for that Contact initiated by CiviCRM will fail to find a matching record in Mailchimp, since the email address was already changed in Mailchimp. 

In this case, an "Mailchimp_Email_NotExists" error along with which email address is in error will be reported in the CiviCRM log. To correct this issue, it is recommended that an administrator change the Mailchimp version of the email address back to the version in CiviCRM. Then, change the email address in CiviCRM and it will be synced back to Mailchimp once the Scheduled Job runs.

## Known Issues

#### Smart Groups

This extension does not currently support Smart Groups but may in a future release. The reason for this is that Smart Groups lie outside the paradigm that Mailchimp expects for a mailing list, where a user has the opportunity to unsubscribe from the list. With a Smart Group, there is no way for an end-user to unsubscribe, so Smart Groups must be treated differently. We are exploring the possibility of treating Smart Group syncing as a one-off action, to be used for things like fundraising thank-you emails.

#### Custom Mailchimp Merge Fields

Mailchimp allows for capturing arbitrary data in custom merge fields. This extension currently only syncs the First Name and Last Name merge fields to/from Mailchimp. 

There is **EXPERIMENTAL** support for defining a custom set of merge fields in the civicrm.settings.php as an array value for $civicrm_setting['CiviMailchimp Preferences']['mailchimp_merge_fields'].

The default merge fields array is:

```
array(
  'FNAME' => 'first_name',
  'LNAME' => 'last_name',
);
```

The key is the Mailchimp merge field tag and the value is the CiviCRM field name.

If you wish to override them, the value for your civicrm.settings.php should look like:

```
$civicrm_setting['CiviMailchimp Preferences']['mailchimp_merge_fields'] = array(
  'YOUR_MAILCHIMP_LIST_ID' => array(
    'FNAME' => 'first_name',
    'LNAME' => 'last_name',
   ),
);
```

Note that the custom value must be keyed by the Mailchimp List ID. You can have multiple Lists defined with the same or different merge fields.

This custom functionality does not currently work for complex Contact record data structures, like addresses, email addresses, phone numbers, etc. 
