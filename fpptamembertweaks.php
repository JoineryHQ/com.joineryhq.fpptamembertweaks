<?php

require_once 'fpptamembertweaks.civix.php';
// phpcs:disable
use CRM_Fpptamembertweaks_ExtensionUtil as E;
// phpcs:enable

/**
 * Month-day (MM/DD) of the date each year on which membership renewals should
 * open.
 */
define('FPPTAMEMBERTWEAKS_RENEWAL_OPEN_DATE', '10/15');

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
      if (_fpptamembertweaks_membership_is_protected_type($objectRef)) {
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
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_pageRun
 */
function fpptamembertweaks_civicrm_pageRun($page) {
  $pageName = $page->getVar('_name');
  if ($pageName == 'CRM_Contact_Page_View_UserDashBoard') {
    // Hide renewal where appropriate for active memberships
    $activeMembers = $page->get_template_vars('activeMembers');
    foreach ($activeMembers as &$activeMember) {
      if (_fpptamembertweaks_is_renewal_disallowed($activeMember)) {
        unset($activeMember['renewPageId']);
      }
    }
    $page->assign('activeMembers', $activeMembers);

    // Hide renewal where appropriate for inactive memberships
    $inActiveMembers = $page->get_template_vars('inActiveMembers');
    foreach ($inActiveMembers as &$inActiveMember) {
      if (_fpptamembertweaks_is_renewal_disallowed($inActiveMember)) {
        unset($inActiveMember['renewPageId']);
      }
    }
    $page->assign('inActiveMembers', $inActiveMembers);

    // Replace "Renew Now" in renew links with "Renew for YYYY", where YYYY is a
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
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function fpptamembertweaks_civicrm_xmlMenu(&$files) {
  _fpptamembertweaks_civix_civicrm_xmlMenu($files);
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
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function fpptamembertweaks_civicrm_postInstall() {
  _fpptamembertweaks_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function fpptamembertweaks_civicrm_uninstall() {
  _fpptamembertweaks_civix_civicrm_uninstall();
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
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function fpptamembertweaks_civicrm_disable() {
  _fpptamembertweaks_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function fpptamembertweaks_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _fpptamembertweaks_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function fpptamembertweaks_civicrm_managed(&$entities) {
  _fpptamembertweaks_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function fpptamembertweaks_civicrm_caseTypes(&$caseTypes) {
  _fpptamembertweaks_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function fpptamembertweaks_civicrm_angularModules(&$angularModules) {
  _fpptamembertweaks_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function fpptamembertweaks_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _fpptamembertweaks_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function fpptamembertweaks_civicrm_entityTypes(&$entityTypes) {
  _fpptamembertweaks_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_themes().
 */
function fpptamembertweaks_civicrm_themes(&$themes) {
  _fpptamembertweaks_civix_civicrm_themes($themes);
}

/**
 * Get the unix timestamp of midnight beginning the day of FPPTAMEMBERTWEAKS_RENEWAL_OPEN_DATE
 * in the current year.
 * @return Boolean
 */
function _fpptamembertweaks_get_current_year_renewal_open_timestamp() {
  return strtotime(FPPTAMEMBERTWEAKS_RENEWAL_OPEN_DATE);
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
 * Determine whether a given membership can be renewed today.
 *
 * @param array $membership A membership as returned by api3 membership.getSingle
 * @return boolean
 */
function _fpptamembertweaks_is_renewal_disallowed(array $membership) {
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
  $canRenewTimestamp = strtotime($expirationYear . '/' . FPPTAMEMBERTWEAKS_RENEWAL_OPEN_DATE);
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
        if (_fpptamembertweaks_is_renewal_disallowed($membership)) {
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
