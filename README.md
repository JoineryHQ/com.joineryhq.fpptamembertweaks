# CiviCRM: FPPTA CiviCRM Membership Tweaks (com.joineryhq.fpptamembertweaks)

Specialized membership-related CiviCRM modifications for FPPTA:

* When a user submits a paid contribution (i.e., paid via credit card) for membership
renewal on an Associate or Pension Board membership, the end date of that membership
is adjusted to Dec. 31 of the coming year. (Note that when a staff member alters a
membership via back-end forms no custom behavior is applied so that, membership End Date
is saved as given.)
* On the CiviCRM user dashboard (https://drupal.example.org/civicrm/user):
  * For any given row under the Memberships section, the "Renew" link will be hidden
    except where all of these criteria are met:
    * The membership End Date is on or before Dec. 31 of the current year.
    * Today's date is after Oct. 15 in the current year.
  * Any displayed "Renew" link will be labeled "Renew for YYYY", where YYYY is a
    4-digit representation of the coming year (e.g. in 2021 this link would be labeled "Renew for 2022".

The extension is licensed under [GPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.0+
* CiviCRM 5.0

## Installation

Please follow [the usual instructions for installing a CiviCRM extension](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/#installing-a-new-extension).

## Usage

No configuration is necessary. This extension performs its work automatically when enabled.

## Support
![Joinery logo](/images/joinery-logo.png)

Joinery provides services for CiviCRM including custom extension development,
training, data migrations, and more. We aim to keep this extension in good
working order, and will do our best to respond appropriately to issues reported
on its [github issue queue](https://github.com/twomice/com.joineryhq.fpptamembertweaks/issues).
In addition, if you require urgent or highly customized improvements to this
extension, we may suggest conducting a fee-based project under our standard
commercial terms.  In any case, the place to start is the
[github issue queue](https://github.com/twomice/com.joineryhq.fpptamembertweaks/issues)
-- let us hear what you need and we'll be glad to help however we can.

And, if you need help with any other aspect of CiviCRM -- from hosting to custom
development to strategic consultation and more -- please contact us directly via
https://joineryhq.com
