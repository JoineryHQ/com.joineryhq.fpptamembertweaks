# CiviCRM: FPPTA CiviCRM Membership Tweaks (com.joineryhq.fpptamembertweaks)

Specialized membership-related CiviCRM modifications for FPPTA:

* Defines a setting "Membership renewal open date" (e.g. "10/15") indicating
  the day-of-year (as MM/DD) on which membership renewals are opened for the coming year.
* When a user submits a paid contribution (i.e., paid via credit card) for membership
renewal on an Associate or Pension Board membership, the end date of that membership
is adjusted to Dec. 31 of the coming year. (Note that when a staff member alters a
membership via back-end forms no custom behavior is applied, so that membership End Date
is saved as given.)
* On the CiviCRM user dashboard (https://drupal.example.org/civicrm/user):
  * For any given row under the Memberships section, the "Renew" link will be displayed
    if and only if all of these criteria are met:
    * The membership End Date is on or before Dec. 31 of the current year.
    * Today's date is after "Membership renewal open date" in the current year.
    * The membership is on-behalf-of an organization.
  * Any displayed "Renew" link will be labeled "Renew for YYYY", where YYYY is a
    4-digit representation of the coming year (e.g. in 2021 this link would be labeled "Renew for 2022".
* On the membership renewal contribution page:
  * If a membership id is provided in query params, attempt to pre-set the
    on-behalf-of field to the appropriate organization, if any. (CiviCRM seem to
    merely leave this selected on the first available option, which is likely to
    be incorrect.)

The extension is licensed under [GPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.0+
* CiviCRM 5.0

## Installation

Please follow [the usual instructions for installing a CiviCRM extension](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/#installing-a-new-extension).

## Usage

No configuration is necessary. This extension performs its work automatically when enabled.

## Support

Support for this package is handled under Joinery's ["As-Is Support" policy](https://joineryhq.com/software-support-levels#as-is-support).

Public issue queue for this package: https://github.com/JoineryHQ/com.joineryhq.fpptamembertweaks/issues