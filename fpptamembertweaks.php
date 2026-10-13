<?php

require_once 'fpptamembertweaks.civix.php';
// phpcs:disable
use CRM_Fpptamembertweaks_ExtensionUtil as E;
// phpcs:enable

/**
 * Month-day (MM/DD) of the date each year on which membership renewals should
 * open.
 */
define('FPPTAMEMBERTWEAKS_PROTECTED_MEMBERSHIP_TYPE_IDS', [1, 2]);

/**
 * Implements hook_civicrm_pre().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_pre
 */
function fpptamembertweaks_civicrm_pre($op, $objectName, $objectId, &$params) {
  if ($objectName == 'Membership'
    && ($op == 'create' || $op == 'edit')
  ) {
    if (
      // If we're in the context of a front-end contribution page
      $_REQUEST['q'] == 'civicrm/contribute/transact'
      // and a payment processor is selected (i.e., this is not a "pay later" contribution)
      && (!$params['contribution']->is_pay_later)
      // and this is the primary member (i.e., not a related membership)
      && (empty($params['owner_membership_id']) || $params['owner_membership_id'] == $objectId)
      // and this is not a test transaction
      && (!$params['is_test'])
    ) {
      if (_fpptamembertweaks_membership_is_protected_type($params)) {
        $params['end_date'] = _fpptamembertweaks_get_end_date_for_current_renewal_period();
      }
    }
  }
}

/**
 * Implements hook_civicrm_buildAmount().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_buildAmount
 */
function fpptamembertweaks_civicrm_buildAmount($pageType, &$form, &$amount) {
  // Only on the contribution page main form:
  if (get_class($form) == 'CRM_Contribute_Form_Contribution_Main') {
    // Initialize array of vars to pass to JS.
    $jsVars = [];
    // Loop through all price fields looking for any that are tied to memberships.
    foreach ($amount as $fieldId => &$field) {
      foreach ($field['options'] as $optionId => $option) {
        if ($optionMembershipTypeId = $option['membership_type_id']) {
          if (in_array($optionMembershipTypeId, FPPTAMEMBERTWEAKS_PROTECTED_MEMBERSHIP_TYPE_IDS)) {
            // Note that we've found a field with membership options for protected member types.
            $protectedMembershipTypeFieldId = $fieldId;
            // Specify field id in jsvars, so JS code can identify this field.
            $jsVars['membershipTypeFieldId'] = $protectedMembershipTypeFieldId;
            // Assume there's only one such field, so break out of both foreach loops.
            break 2;
          }
        }
      }
    }
    // If we have a field with membership options for protected member types:
    if (!empty($protectedMembershipTypeFieldId)) {
      // Get list of disallowed types per org, and pass to JS.
      $jsVars['disallowedMembershipTypeMessagesPerOrg'] = _fpptamembertweaks_get_disallowed_membership_type_messages_per_org_in_form($form);
      // Add JS file and vars.
      CRM_Core_Resources::singleton()->addScriptFile('com.joineryhq.fpptamembertweaks', 'js/CRM_Contribute_Form_Contribution_Main.js');
      CRM_Core_Resources::singleton()->addVars('fpptamembertweaks', $jsVars);
    }

  }
}

/**
 * Implements hook_civicrm_pageRun().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_buildForm
 */
function fpptamembertweaks_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Contribute_Form_Contribution_Main') {
    // If a membership id is provided in query params, attempt to pre-set the
    // on-behalf-of field to the appropriate organization, if any.
    $mid = (CRM_Utils_Request::retrieve('mid', 'Positive', $form) ?: FALSE);
    if (!$mid) {
      return;
    }
    $membershipGet = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'id' => ($mid ?? -1),
      'api.Membership.get' => ['id' => "\$value.owner_membership_id"],
    ]);
    if ($ownerContactId = $membershipGet['values'][0]['api.Membership.get']['values'][0]['contact_id']) {
      $defaultValues = [
        'onbehalfof_id' => $ownerContactId,
      ];
      $form->setDefaults($defaultValues);
    }
  }
}

/**
 * Implements hook_civicrm_pageRun().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_pageRun
 */
function fpptamembertweaks_civicrm_pageRun($page) {
  $pageName = $page->getVar('_name');
  if ($pageName == 'CRM_Contact_Page_View_UserDashBoard') {
    // Show/Hide renewal link where appropriate for active memberships
    $activeMembers = $page->get_template_vars('activeMembers');
    foreach ($activeMembers as &$activeMember) {
      _fpptamembertweaks_alter_renewal_links($activeMember);
    }
    $page->assign('activeMembers', $activeMembers);

    // Show/Hide renewal link where appropriate for inactive memberships
    $inActiveMembers = $page->get_template_vars('inActiveMembers');
    foreach ($inActiveMembers as &$inActiveMember) {
      _fpptamembertweaks_alter_renewal_links($inActiveMember);
    }
    $page->assign('inActiveMembers', $inActiveMembers);

    // Prepare to replace (via JS) "Renew Now" in renew links with "Renew for YYYY", where YYYY is a
    // 4-digit representation of the coming year.
    // If we don't use addString(), this string may not be replaced using ts() in JavaScript.
    CRM_Core_Resources::singleton()->addString('Renew Now');
    $renewForNextYearLabel = E::ts('Renew for %1', [
      '1' => date('Y', strtotime(_fpptamembertweaks_get_end_date_for_current_renewal_period())),
    ]);
    CRM_Core_Resources::singleton()->addVars('fpptamembertweaks', ['renewForNextYearLabel' => $renewForNextYearLabel]);
    CRM_Core_Resources::singleton()->addScriptFile('com.joineryhq.fpptamembertweaks', 'js/CRM_Contact_Page_View_UserDashBoard.js');
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function fpptamembertweaks_civicrm_config(&$config) {
  _fpptamembertweaks_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function fpptamembertweaks_civicrm_install() {
  _fpptamembertweaks_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function fpptamembertweaks_civicrm_enable() {
  _fpptamembertweaks_civix_civicrm_enable();
}

/**
 * Get the unix timestamp of midnight beginning the day of 'fpptamembertweaks_renewal_open_date'
 * in the current year.
 * @return Boolean
 */
function _fpptamembertweaks_get_current_year_renewal_open_timestamp() {
  return strtotime(CRM_Fpptamembertweaks_Utils_Settings::get('fpptamembertweaks_renewal_open_date'));
}

/**
 * Determine whether or not we have passed the renewals open date for the current year.
 * @return Boolean
 */
function _fpptamembertweaks_is_current_year_renewal_cutoff_passed() {
  return (time() >= _fpptamembertweaks_get_current_year_renewal_open_timestamp());
}

/**
 * Use the current date to determine the appropriate end_date for memberships
 * renewed today.
 *
 * @return string Date in the format 'YYYYMMDD'
 */
function _fpptamembertweaks_get_end_date_for_current_renewal_period() {
  // Is today on or after the cutoff date?
  if (_fpptamembertweaks_is_current_year_renewal_cutoff_passed()) {
    // End date for renewal period is dec. 31 of next year.
    $endDate = date('Y', strtotime('+1 year')) . '1231';
  }
  else {
    // End date for renewal period is dec. 31 of this year.
    $endDate = date('Y') . '1231';
  }
  return $endDate;
}

/**
 * Determine whether a given membership can be renewed, based on whether its primary
 * contact is an orgnization.
 *
 * @param array $membership A membership as returned by api3 membership.getSingle
 *   -- must have 'owner_membership_id' array key.
 * @return boolean
 */
function _fpptamembertweaks_is_renewal_disallowed_per_primary_member(array $membership) {
  if (empty($membership['owner_membership_id'])) {
    // owner_membership_id empty or missing, so return false.
    return FALSE;
  }
  $membershipGet = civicrm_api3('Membership', 'get', [
    'sequential' => 1,
    'id' => ($membership['owner_membership_id'] ?? -1),
    'api.Contact.get' => ['id' => "\$value.contact_id", 'contact_type' => "organization"],
  ]);

  $ret = (bool) ($membershipGet['values'][0]['api.Contact.get']['id'] ?? FALSE);
  return $ret;
}

/**
 * Determine whether a given membership can be renewed today.
 *
 * @param array $membership A membership as returned by api3 membership.getSingle
 * @return boolean
 */
function _fpptamembertweaks_is_renewal_disallowed_per_date(array $membership) {
  // If the current time is less than the time when renewal is allowed, then disallow.
  $now = time();
  $canRenewTimestamp = _fpptamembertweaks_get_timestamp_when_membership_can_renew($membership);
  $disallow = ($now < $canRenewTimestamp);
  return $disallow;
}

/**
 * Get a timestemp representing midnight beginning the first day when a given
 * membership can be renewed.
 *
 * @param array $membership A membership as returned by api3 membership.getSingle
 * @return int
 */
function _fpptamembertweaks_get_timestamp_when_membership_can_renew(array $membership) {
  if (!_fpptamembertweaks_membership_is_protected_type($membership)) {
    // If this is not a protected type, we'll let them renew anytime.
    $timestamp = time();
  }
  // Otherwise, they can renew after renewal-open-date in the year of their expiration.
  $expirationYear = date('Y', strtotime($membership['end_date']));
  $canRenewTimestamp = strtotime($expirationYear . '/' . CRM_Fpptamembertweaks_Utils_Settings::get('fpptamembertweaks_renewal_open_date'));
  return $canRenewTimestamp;
}

/**
 * Determine whether a given membership is of a type for which we're protecting renewals
 * (Associate and Pension Board).
 *
 * @param array|object $membership
 *    A membership, either as an object of type CRM_Member_DAO_Membership, or
 *    an array as returned by api3 membership.getSingle.
 * @return bool
 */
function _fpptamembertweaks_membership_is_protected_type($membership) {
  // convert membership to array if it's an object.
  $membership = (array) $membership;
  // Ensure we're only doing this for associate and pension_board memberships.
  $protectedTypeIds = FPPTAMEMBERTWEAKS_PROTECTED_MEMBERSHIP_TYPE_IDS;
  return (in_array(strtolower($membership['membership_type_id']), $protectedTypeIds));
}

function _fpptamembertweaks_alter_renewal_links(&$membership) {
  if (
    (! _fpptamembertweaks_is_renewal_disallowed_per_primary_member($membership))
    && (! _fpptamembertweaks_is_renewal_disallowed_per_date($membership))
  ) {
    unset($membership['renewMembershipLink']);
  }
  else {
    $membership['renewMembershipLink'] = CRM_Utils_System::url('civicrm/contribute/transact', "reset=1&id={$membership['renewPageId']}&mid={$membership['id']}", TRUE, NULL, FALSE, TRUE);
  }
}

/**
 * For a given membership type ID, get all memberships of that type held by
 * the current user.
 *
 * @param int $membershipTypeId
 * @return array Array of membership types, each one an array as returned by api3 membership.getSingle
 */
function _fpptamembertweaks_get_user_memberships_of_type($membershipTypeId) {
  $cid = CRM_Core_Session::singleton()->getLoggedInContactID();
  $membershipGet = civicrm_api3('Membership', 'get', [
    'contact_id' => $cid,
    'membership_type_id' => $membershipTypeId,
  ]);
  return $membershipGet['values'];
}

/**
 * For each "on behalf of" organization named in the form, for any memberships the org
 * has which are disallowed for renewal, create a warning message for that org/member-type
 * and return those messages in an array.
 * @param object $form Instance of CRM_Core_Form (most likely CRM_Contribute_Form_Contribution_Main)
 * @return array
 *   Array of messages keyed to orgCid and membershipTypeId, e.g.
 *   $return[$orgCid][$membershipTypeId] = 'message';
 */
function _fpptamembertweaks_get_disallowed_membership_type_messages_per_org_in_form($form) {
  // Initialize an empty array.
  $disallowedMembershipTypeMessagesPerOrg = [];

  // Get names for our protected membership types.
  $membershipTypeNames = [];
  foreach (FPPTAMEMBERTWEAKS_PROTECTED_MEMBERSHIP_TYPE_IDS as $membershipTypeId) {
    $membershipTypeNames[$membershipTypeId] = civicrm_api3('MembershipType', 'getvalue', [
      'return' => "name",
      'id' => $membershipTypeId,
    ]);
  }

  // Get all the organization IDs for "on behalf" existing organizations in the form (assuming form has "on behalf" enabled); without it, this feature won't do anything at all.
  if ($form->elementExists('onbehalfof_id')) {
    $el = $form->getElement('onbehalfof_id');
    $options = $el->_options;
    // Loop through these options.
    foreach ($options as $option) {
      $orgCid = $option['attr']['value'];
      // Get all (protected-type) memberships for this organization.
      $membershipGet = civicrm_api3('Membership', 'get', [
        'contact_id' => $orgCid,
        'membership_type_id' => ['IN' => FPPTAMEMBERTWEAKS_PROTECTED_MEMBERSHIP_TYPE_IDS],
      ]);
      foreach ($membershipGet['values'] as $membership) {
        // For each found membership, if it's disallowed for renewing, formulate
        // a message to inform the user of such.
        if (_fpptamembertweaks_is_renewal_disallowed_per_date($membership)) {
          $disallowedMembershipTypeMessagesPerOrg[$orgCid][$membership['membership_type_id']] = E::ts('The <strong>%1</strong> membership for this organization does not expire until %2. You may not renew it until %3.', [
            '1' => $membershipTypeNames[$membership['membership_type_id']],
            '2' => CRM_Utils_Date::customFormat($membership['end_date']),
            '3' => CRM_Utils_Date::customFormat(date('Y-m-d', _fpptamembertweaks_get_timestamp_when_membership_can_renew($membership))),
          ]);
        }
      }
    }
  }
  return $disallowedMembershipTypeMessagesPerOrg;
}
