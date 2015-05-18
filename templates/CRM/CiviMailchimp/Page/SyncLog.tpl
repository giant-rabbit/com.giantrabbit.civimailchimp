<div id="help">
  {ts}This page will list the <strong>100 most recent</strong> CiviMailchimp sync log
messages. Older messages can be viewed in the standard CiviCRM log file.{/ts}
</div>
<div>
  <a href="/civicrm/admin/mailchimp/log/clear-all-messages">Clear all unread messages.</a>
</div>
<table cellpadding="0" cellspacing="0" border="0">
  <tr class="columnheader">
    <th>{ts}Type{/ts}</th>
    <th>{ts}Direction{/ts}</th>
    <th>{ts}Message{/ts}</th>
    <th>{ts}Timestamp{/ts}</th>
  </tr>
  {foreach from=$rows item=row}
    <tr id="sync-log-{$row.id}" class="crm-entity {cycle values="odd-row,even-row"}">
      <td class="crm-admin-sync-log-type">{$row.type}</td>
      <td class="crm-admin-sync-log-direction">{$row.direction}</td>
      <td class="crm-admin-sync-log-message">{$row.message}{if $row.details}<br /><br /><a class="show-hide" href="#">show/hide details</a><div style="display: none;"><pre>{$row.details}</pre></div>{/if}</td>
      <td class="crm-admin-sync-log-timestamp">{$row.timestamp}</td>
    </tr>
  {/foreach}
</table>
