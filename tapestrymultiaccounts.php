<?php

require_once 'tapestrymultiaccounts.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function tapestrymultiaccounts_civicrm_config(&$config) {
  _tapestrymultiaccounts_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function tapestrymultiaccounts_civicrm_xmlMenu(&$files) {
  _tapestrymultiaccounts_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function tapestrymultiaccounts_civicrm_install() {
  _tapestrymultiaccounts_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function tapestrymultiaccounts_civicrm_uninstall() {
  _tapestrymultiaccounts_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function tapestrymultiaccounts_civicrm_enable() {
  _tapestrymultiaccounts_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function tapestrymultiaccounts_civicrm_disable() {
  _tapestrymultiaccounts_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function tapestrymultiaccounts_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _tapestrymultiaccounts_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function tapestrymultiaccounts_civicrm_managed(&$entities) {
  _tapestrymultiaccounts_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function tapestrymultiaccounts_civicrm_caseTypes(&$caseTypes) {
  _tapestrymultiaccounts_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function tapestrymultiaccounts_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _tapestrymultiaccounts_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_alterPaymentProcessorParams().
 *
 * The goal is to look at the transactions and check that all line items belong to
 * the payment processor's organization (ie. ATW of TFW). The mapping between
 * the contact id's of these 2 organisations and their eWay processors is
 * hard-coded into this extension.
 *
 * If not then split off the portion that doesn't into a separate eway payment
 * and adjust the total of the eWay payment about
 * to be processed.
 *
 * The contribution is not 'aware' that the payment has been split. This payment
 * information is not stored in CiviCRM.
 *
 * If all lines in this payment belong to the other organisation the whole thing
 * is pushed that way.
 *
 * @param CRM_Core_Payment $paymentObj
 * @param array $rawParams
 * @param obj $cookedParams
 */
function tapestrymultiaccounts_civicrm_alterPaymentProcessorParams($paymentObj, &$rawParams, &$cookedParams) {
  dpm($paymentObj);
  dpm($rawParams);
  dpm($cookedParams);
  // We need to avoid a loop so we don't run this code on the same invoiceID twice.
  static $invoices = array();
  if (in_array($cookedParams->txInvoiceReference, $invoices)) {
    return;
  }
  $invoices[] = $cookedParams->txInvoiceReference;

  $processorContactID = tapestrymultiaccounts_get_processor_contact_id($paymentObj);

  // The contribution may or may not exist at this point.
  // Back office event is a situation where it does not exist. We can test by
  /* checking if the allocated invoice_id exists.

  $isCreated = CRM_Core_DAO::singleValueQuery(
    'SELECT * FROM civicrm_contribution WHERE invoice_id = %1',
    array(1 => array($cookedParams->txInvoiceReference, 'String'))
  );
  */

  $accountContactLines = tapestrymultiaccounts_get_lines_by_account_contact($rawParams);

  // We have 3 scenarios here.
  // - A split payment.
  // - A payment headed to the correct processor
  // - A payment headed to the wrong processor

  if (count($accountContactLines) > 1) {
    foreach ($accountContactLines as $contact => $lines) {
      $priceFields = array();
      foreach ($lines as $fieldID => $values) {
        $priceFields['price_' . $fieldID] = $rawParams['price_' . $fieldID];
      }
      $amount = tapestrymultiaccounts_get_lines_total($priceFields);

      if ($contact != $processorContactID) {
        // Here we need to add a new payment with these line items
        $mappedParams = array(
          'contactID',
          'invoiceID',
          'first_name',
          'middle_name',
          'last_name',
          'street_address',
          'postal_code',
          'city',
          'state_province',
          'country',
          'description',
          'credit_card_number',
          'month',
          'year',
          'cvv2',
          'ip_address',
        );
        $newContributionParams = array_intersect_key($rawParams, array_fill_keys($mappedParams, 1));

        $newContributionParams['amount'] = $amount;
        $processorInstance = Civi\Payment\System::singleton()->getById(
          tapestrymultiaccounts_get_equivalent_processor_id(tapestrymultiaccounts_get_processor_id($paymentObj)
        ));
        $processorInstance->doPayment($newContributionParams);
      }
      else {
        // Adjust total down to revised total.
        $cookedParams->txAmount = tapestrymultiaccounts_get_amount_in_cents($amount);
      }
    }
  }
  elseif (empty($accountContactLines[$processorContactID])) {
    // It's going to the wrong gateway.
    $originalProcessor = $paymentObj->getPaymentProcessor();
    $processor = array_merge($originalProcessor, tapestrymultiaccounts_get_equivalent_processor($originalProcessor['id']));
    $paymentObj->setPaymentProcessor($processor);
    $cookedParams->EwayCustomerID($processor['subject']);
  }
  else {
    // No change required.
  }
}

/**
 * Get amount in cents.
 *
 * This is copied from eWay account
 *
 * eg. 100 for $1
 *
 * @param string $amount
 *   Amount in currency format
 *
 * @return float
 */
function tapestrymultiaccounts_get_amount_in_cents($amount) {
  $amountInCents = round(((float) $amount) * 100);
  return $amountInCents;
}

/**
 * Get prices set ID for price field.
 *
 * @param $priceFieldID
 *
 * @return int
 * @throws \CiviCRM_API3_Exception
 */
function tapestrymultiaccounts_get_price_set_id($priceFieldID) {
  static $priceFields = array();
  if (!in_array($priceFieldID, $priceFields)) {
    $priceFields[$priceFieldID] = (int) civicrm_api3('price_field', 'getvalue', array(
        'id' => $priceFieldID,
        'return' => 'price_set_id',
      )
    );
  }
  return $priceFields[$priceFieldID];
}

/**
 * Get total costs of specified line items.
 *
 * We are trying to create this on a minimal set of information from the
 * sort of format known to a form with a priceset on it.
 * such as
 *
 *   'price_5' => 1,
 *   'price_8' => array(6, 7, 8)
 *
 * @param array $priceParams
 *
 * @return float
 */
function tapestrymultiaccounts_get_lines_total($priceParams) {
  foreach ($priceParams as $field => $value) {
    $priceFieldID = substr($field, 6);
    $priceSetID = tapestrymultiaccounts_get_price_set_id($priceFieldID);
    $priceSetDetail = CRM_Price_BAO_PriceSet::getSetDetail($priceSetID, FALSE, FALSE);
    $priceSetFieldSpec = $priceSetDetail[$priceSetID]['fields'];
    //$priceFields = _civicrm_api3_order_get_field_values_for_field_id($priceFieldID, $priceFields, $field);
    if ($priceSetFieldSpec[$priceFieldID]['html_type'] == 'Text' && is_array($priceParams['price_' . $priceFieldID])) {
      // This is a quantity field e.g
      // array( 26 => 3) where 3 is the quantity purchased
      // the processAmount function assumes that this needs to be turned
      // in an array so we de-array-ify it first
      $priceParams['price_' . $priceFieldID] = reset($priceParams['price_' . $priceFieldID]);
    }
    $priceFields = array($field => array());
    CRM_Price_BAO_PriceSet::processAmount($priceSetFieldSpec, $priceParams, $priceFields[$field]['line_item']);
  }
  return $priceParams['amount'];
}

/**
 * Get the contact relevant to the particular processor.
 *
 * We are using a hard-coded mapping here and don't have any current plans
 * to create a data model for this.
 *
 * @param CRM_Core_Payment $paymentObj
 *
 * @return int
 */
function tapestrymultiaccounts_get_processor_contact_id($paymentObj) {
  $processorID = tapestrymultiaccounts_get_processor_id($paymentObj);
  $processorToOrganizationMapping = tapestrymultiaccounts_get_civicrm_processor_mapping();
  return $processorToOrganizationMapping[$processorID];
}

/**
 * Get the payment processor ID from the payment object.
 *
 * @param CRM_Core_Payment $paymentObj
 *
 * @return int
 */
function tapestrymultiaccounts_get_processor_id($paymentObj) {
  $processor = $paymentObj->getPaymentProcessor();
  $processorID = $processor['id'];
  return $processorID;
}

/**
 * Get mapping of payment processor id to civicrm contact.
 *
 * This is a hard-coded mapping of which processors belong to which organizations.
 *
 * @return array
 */
function tapestrymultiaccounts_get_civicrm_processor_mapping() {
  return array(
    1 => 1,
    2 => 1,
    3 => 715,
    4 => 715,
  );
}

/**
 * Get equivalent processor from the other organisation.
 *
 * ie. if we have processor 1 that is the ATW live processor so we return
 * processor 3 - the TFW live processor.
 *
 * Sticking with hard coding for now but hard-coding is only in these 2 functions
 * so easy to swap out.
 *
 * @param int $processorID
 *
 * @return int
 */
function tapestrymultiaccounts_get_equivalent_processor_id($processorID) {
  $mapping = array(
    1 => 3,
    2 => 4,
    3 => 1,
    4 => 2,
  );
  return $mapping[$processorID];

}

/**
 * Get equivalent processor from the other organisation.
 *
 * ie. if we have processor 1 that is the ATW live processor so we return
 * processor 3 - the TFW live processor.
 *
 * Sticking with hard coding for now but hard-coding is only in these 2 functions
 * so easy to swap out.
 *
 * @param int $processorID
 *
 * @return array
 */
function tapestrymultiaccounts_get_equivalent_processor($processorID) {
  return civicrm_api3('payment_processor', 'getsingle', array(
    'id' => tapestrymultiaccounts_get_equivalent_processor_id($processorID),
  ));

}

/**
 * Get line items separated by account contact.
 *
 * @param array $rawParams
 *
 * @return array
 *   Line items keyed by account contact.
 *
 * @throws \CiviCRM_API3_Exception
 */
function tapestrymultiaccounts_get_lines_by_account_contact($rawParams) {
  $priceLines = array();
  $accountContactLines = array();
  foreach ($rawParams as $fieldName => $values) {
    if (substr($fieldName, 0, 6) == 'price_') {
      // This is a line_item parameter.
      $priceFieldID = substr($fieldName, 6);
      $priceLines[$priceFieldID] = civicrm_api3('price_field', 'getsingle', array(
          'id' => $priceFieldID,
        )
      );

      foreach ($values as $priceFieldValueID => $value) {
        $priceFieldValue = civicrm_api3(
          'price_field_value',
          'getsingle',
          array('id' => $priceFieldValueID));
        if ($priceLines[$priceFieldID]['name'] == 'contribution_amount'
          && !empty($priceFieldValue['financial_type_id'])
          && (civicrm_api3('price_set', 'getvalue', array(
            'id' => $priceLines[$priceFieldID]['price_set_id'],
            'return' => 'name')) == 'default_contribution_amount')
        ) {
          $priceFieldValue['financial_type_id'] = $rawParams['financial_type_id'];
        }
        $priceLines[$priceFieldID]['values'][$priceFieldValueID] = $priceFieldValue;
        $priceLines[$priceFieldID]['values'][$priceFieldValueID]['input'] = $value;
        $accountsContactID = tapestrymultiaccounts_get_civicrm_financial_contact($priceFieldValue['financial_type_id']);
        $accountContactLines[$accountsContactID][$priceFieldID] = $priceLines[$priceFieldID];

      }

    }
  }
  return $accountContactLines;
}

/**
 * Get the financial_contact from the financial type id.
 *
 * @param int $financialTypeID
 *
 * @return mixed
 */
function tapestrymultiaccounts_get_civicrm_financial_contact($financialTypeID) {
  static $contacts = array();
  if (!in_array($financialTypeID, $contacts)) {
    $accountingCode = tapestrymultiaccounts_get_civicrm_account_code($financialTypeID);
    $contacts[$financialTypeID] = CRM_Core_DAO::singleValueQuery(
      "SELECT contact_id FROM civicrm_financial_account
       WHERE accounting_code = %1
     ",
      array(1 => array($accountingCode, 'String'))
    );
  }
  return $contacts[$financialTypeID];
}

/**
 * Get the accounting code from the financial type id.
 *
 * @param int $financialTypeID
 *
 * @return mixed
 */
function tapestrymultiaccounts_get_civicrm_account_code($financialTypeID) {
  static $codes = array();
  if (!in_array($financialTypeID, $codes)) {
    $codes[$financialTypeID] = CRM_Financial_BAO_FinancialAccount::getAccountingCode($financialTypeID);
  }
  return $codes[$financialTypeID];
}

/**
 * Functions below this ship commented out. Uncomment as required.
 *

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function tapestrymultiaccounts_civicrm_preProcess($formName, &$form) {

}

*/
