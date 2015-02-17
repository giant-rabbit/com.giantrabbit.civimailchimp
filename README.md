CiviMailchimp Sync
==================

## What It Does

The CiviMailchimp extension allows CiviCRM Groups to sync to Mailchimp Lists. If a Group is configured to sync to a Mailchimp List any Contact's in that Group will be synced to Mailchimp. Note that only Contacts with an email address that is not On Hold and where the Contact is not marked Do Not Email will be Synced. In addition, if a Contact meets that criteria and has more than one email address, if one of their emails is marked as Bulk Mailings, that email address will be the one synced. Otherwise, the Primary Email address will be synced.


## What It Doesn't Do


## What Makes It Different

There are a couple of other extensions that handle syncing with Mailchimp, but we decided to start from scratch with this one for a couple of reasons.

Tests! We determined that the best way to ensure that the extension was working was to have as much of the code covered by automated tests as possible, which we've done.

Logging and Error Handling! Sometimes things do go wrong, and when they do, we decided it's best to have them fail spectacularly. Throughout the code, we've added error handling and logging that surfaces errors to both the CiviCRM log and administrative error popups as soon as a problem is detected so it can be taken care of quickly. The worst thing is to find out months later that contacts were not being synced properly and having to unravel that mess! Also, when CiviCRM's Debug mode is enabled, all incoming and outgoing requests from/to Mailchimp are logged in the CiviCRM log.


## How To Install and Configure

Follow the standard steps for installing and enabling an extension at http://wiki.civicrm.org/confluence/display/CRMDOC/Extensions.

Once the extension is enabled, enter your Mailchimp API Key by going to Administer > CiviMailchimp > Mailchimp Settings. If you need to use a different base url for Mailchimp's Webhooks than your CiviCRM base url, as you might if your site is behind an .htaccess password, you may enter it here, too. Both of these values can be overriden in your civicrm.settings.php file by following the procedures in http://wiki.civicrm.org/confluence/display/CRMDOC/Override+CiviCRM+Settings. The two values you can override are $civicrm_setting['CiviMailchimp Preferences']['mailchimp_api_key'] and $civicrm_setting['CiviMailchimp Preferences']['mailchimp_webhook_base_url'].

At this point, you should be able to edit a CiviCRM Group and associate it to a Mailchimp List (of course, you should setup at least one List in Mailchimp first). When you edit the Group, you'll see a new "Mailchimp Sync Settings" section where you can select the Mailchimp List the Group should sync with. If you've configured Interest Groups in Mailchimp for this List, you can also select some or all of those, too. Note that only one Mailchimp List can be associated with a CiviCRM Group at the same time.

Changes to Contacts in that Group are added to a CiviCRM Queue. In order to trigger the processing of that Queue, you must configure CiviCRM's Cron as documented at http://wiki.civicrm.org/confluence/display/CRMDOC/Managing+Scheduled+Jobs. Then, enable the "Sync Contacts to Mailchimp" Scheduled Job by going to Administer > System Settings > Scheduled Jobs. You can also manually execute the scheduled job here to debugging and testing purposes.

*** merge fields ***


## Known Issues

There is the possibility that a contact's data in CiviCRM and Mailchimp can get out-of-sync and need to be manually corrected. One scenario where this may occur is if a Contact's email address is changed in CiviCRM and the scheduled job has not run yet. If, in that time, that same Contact's email is changed in Mailchimp, it will change the email address immediately in CiviCRM. However, once the scheduled job runs, the email address change for that Contact initiated by CiviCRM will fail to find a matching record in Mailchimp, since the email address was already changed in Mailchimp. In this case, an error will be logged in CiviCRM's log and the error will be surfaced as an error popup for an admin to deal with.

Other issues can arise if there are multiple contacts with the same email address, specifically, if the duplicate email address is the one that would be used in Mailchimp for those contacts. For example, if two contact's primary email is "test@test.com", if that email is subscribed to a Mailchimp list, only one contact will be added to the Group set to sync with that list, since Mailchimp does not allow duplicate email addresses. In general, it is recommended that contacts with multiple email addresses not be in CiviCRM, or that all but one of the duplicate emails be marked Do Not Email or On Hold.
