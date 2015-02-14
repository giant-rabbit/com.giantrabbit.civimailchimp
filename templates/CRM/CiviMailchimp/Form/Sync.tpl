{* HEADER *}
<div id="help" class="description">
  <p>{ts}Select a group to force sync between Mailchimp and CiviCRM. This
may be necessary when configuring a group to sync with Mailchimp if the group
or the Mailchimp list has existing contacts. Only groups already configured
to sync with Mailchimp will appear in this list.{/ts}</p>
  <p>{ts}Note: This action will not update data for existing contacts. It
will only add them to the specified CiviCRM group if they are a member of the
Mailchimp list configured to sync with that group. New contacts will get an
email address, first name and last name, depending on whether the data exists
in Mailchimp.{/ts}</p>
</div>
{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
