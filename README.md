CiviMailchimp Sync
==================

The CiviMailchimp extension allows CiviCRM Groups to sync to Mailchimp Lists. If a Group is configured to sync to a Mailchimp List any Contacts in that Group will be synced to Mailchimp. 

## What Makes It Different

There are a couple of other extensions that handle syncing with Mailchimp, but we decided to start from scratch with this one for a couple of reasons.

Tests! We determined that the best way to ensure that the extension was working was to have as much of the code covered by automated tests as possible, which we've done.

Logging and Error Handling! Sometimes things do go wrong, and when they do, we decided it's best to have them fail spectacularly. Throughout the code, we've added error handling and logging that surfaces errors to the CiviCRM log as soon as a problem is detected so it can be taken care of quickly. The worst thing is to find out months later that contacts were not being synced properly and having to unravel that mess! Also, when CiviCRM's Debug mode is enabled, all incoming and outgoing requests from/to Mailchimp are logged in the CiviCRM log.


## How To Install and Configure

Follow the standard steps for installing and enabling an extension at http://wiki.civicrm.org/confluence/display/CRMDOC/Extensions.

Once the extension is enabled, enter your Mailchimp API Key by going to Administer > CiviMailchimp > Mailchimp Settings. If you need to use a different base url for Mailchimp's Webhooks than your CiviCRM base url, as you might if your site is behind an .htaccess password, you may enter it here, too. Both of these values can be overriden in your civicrm.settings.php file by following the procedures in http://wiki.civicrm.org/confluence/display/CRMDOC/Override+CiviCRM+Settings. The two values you can override are $civicrm_setting['CiviMailchimp Preferences']['mailchimp_api_key'] and $civicrm_setting['CiviMailchimp Preferences']['mailchimp_webhook_base_url'].

At this point, you should be able to edit a CiviCRM Group and associate it to a Mailchimp List (of course, you should setup at least one List in Mailchimp first). When you edit the Group, you'll see a new "Mailchimp Sync Settings" section where you can select the Mailchimp List the Group should sync with. If you've configured Interest Groups in Mailchimp for this List, you can also select some or all of those, too. Note that only one Mailchimp List can be associated with a CiviCRM Group at the same time.

Changes to Contacts in that Group are added to a CiviCRM Queue. In order to trigger the processing of that Queue, you must configure CiviCRM's Cron as documented at http://wiki.civicrm.org/confluence/display/CRMDOC/Managing+Scheduled+Jobs. Then, enable the "Sync Contacts to Mailchimp" Scheduled Job by going to Administer > System Settings > Scheduled Jobs. You can also manually execute the scheduled job here to debugging and testing purposes.

* merge fields *

## Important Architectural Choices

At their cores, CiviCRM and Mailchimp have very different architectural paradigms that make them somewhat tricky to keep records in Sync. Here are some of the major ones and the choices made to mitigate them.

#### Which Email Address To Sync

Mailchimp expects each record to have a single email address. CiviCRM allows Contacts to have multiple email addresses. So, if we're syncing a Contact record to Mailchimp and that Contact has multiple email addresse, which email address should we send to Mailchimp?

The first thing we check is that the Contact is not marked Do Not Email. If they are, then they don't get synced with Mailchimp. If they aren't, then we check to see if they have an email that is marked Bulk Mailings and is not On Hold. If they do, that's the email address we sync. If not, if they have a Primary email address that is not On Hold, then we use that email address. If they don't have that, then we don't sync the Contact to Mailchimp.

#### What About Multiple Contacts With the Same Email

Other issues can arise if there are multiple contacts with the same email address, specifically, if the duplicate email address is the one that would be used in Mailchimp for those contacts. For example, if two contact's primary email is "test@test.com", if that email is subscribed to a Mailchimp list, only one contact will be added to the Group set to sync with that list, since Mailchimp does not allow duplicate email addresses. In general, it is recommended that contacts with multiple email addresses not be in CiviCRM, or that all but one of the duplicate emails be marked Do Not Email or On Hold.

## Troubleshooting

There are a number of scenarios where syncing may cease to operate. Refer to
the CiviCRM log to see any errors reported. These include:

#### Mailchimp Interest Group Renamed/Missing

If a Mailchimp Interest Group was renamed or removed, if a CiviCRM Group is configured to sync with the renamed Interest Group, syncing from CiviCRM to Mailchimp will fail with an "List_InvalidInterestGroup" error along with a message stating which Interest Group is invalid.

To correct this issue, edit the CiviCRM Group set to sync with this Interest Group and select the new Interest Group and save. If the Interest Group was removed, just save the CiviCRM Group. Syncing should now continue properly.

#### Email Address Changed Both in Mailchimp and CiviCRM

There is the possibility that a contact's data in CiviCRM and Mailchimp can get out-of-sync and need to be manually corrected. One scenario where this may occur is if a Contact's email address is changed in CiviCRM and the scheduled job has not run yet. If, in that time, that same Contact's email is changed in Mailchimp, it will change the email address immediately in CiviCRM. However, once the scheduled job runs, the email address change for that Contact initiated by CiviCRM will fail to find a matching record in Mailchimp, since the email address was already changed in Mailchimp. 

In this case, an "Email_NotExists" error along with which email address is in error will be reported in the CiviCRM log. An admin can then manually correct the email address in Mailchimp and CiviCRM so that they are matching and syncing should continue.

## Known Issues

This extension does not currently support Smart Groups but may in a future release. The reason for this is that Smart Groups lie outside the paradigm that Mailchimp expects for a mailing list, where a user has the opportunity to unsubscribe from the list. With a Smart Group, there is no way for an end-user to unsubscribe, so Smart Groups must be treated differently. We are exploring the possibility of treating Smart Group syncing as a one-off action, to be used for things like fundraising thank-you emails.
