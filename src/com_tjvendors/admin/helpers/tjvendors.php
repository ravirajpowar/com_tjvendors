<?php
/**
 * @version    SVN:
 * @package    Com_Tjvendors
 * @author     Techjoomla <contact@techjoomla.com>
 * @copyright  Copyright  2009-2017 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */
// No direct access
defined('_JEXEC') or die;

/**
 * Tjvendors helper.
 *
 * @since  1.6
 */
class TjvendorsHelpersTjvendors
{
	/**
	 * Configure the Linkbar.
	 *
	 * @param   string  $vName  string
	 *
	 * @return void
	 */
	public static function addSubmenu($vName = '')
	{
		$input = JFactory::getApplication()->input;
		$full_client = $input->get('client', '', 'STRING');
		$full_client = explode('.', $full_client);

		$component = $full_client[0];
		$eName = str_replace('com_', '', $component);
		$file = JPath::clean(JPATH_ADMINISTRATOR . '/components/' . $component . '/helpers/' . $eName . '.php');

		if (file_exists($file))
		{
			require_once $file;

			$prefix = ucfirst(str_replace('com_', '', $component));

			$cName = $prefix . 'Helper';

			if (class_exists($cName))
			{
				if (is_callable(array($cName, 'addSubmenu')))
				{
					$lang = JFactory::getLanguage();

					// Loading language file from the administrator/language directory then
					// Loading language file from the administrator/components/*extension*/language directory
					$lang->load($component, JPATH_BASE, null, false, false)
					|| $lang->load($component, JPath::clean(JPATH_ADMINISTRATOR . '/components/' . $component), null, false, false)
					|| $lang->load($component, JPATH_BASE, $lang->getDefault(), false, false)
					|| $lang->load($component, JPath::clean(JPATH_ADMINISTRATOR . '/components/' . $component), $lang->getDefault(), false, false);

					call_user_func(array($cName, 'addSubmenu'), $vName . (isset($section) ? '.' . $section : ''));
				}
			}
		}
	}

	/**
	 * Gets a list of the actions that can be performed.
	 *
	 * @return    JObject
	 *
	 * @since    1.6
	 */
	public static function getActions()
	{
		$user   = JFactory::getUser();
		$result = new JObject;

		$assetName = 'com_tjvendors';

		$actions = array(
			'core.admin', 'core.manage', 'core.create', 'core.edit', 'core.edit.own', 'core.edit.state', 'core.delete'
		);

		foreach ($actions as $action)
		{
			$result->set($action, $user->authorise($action, $assetName));
		}

		return $result;
	}

	/**
	 * Get array of unique Clients
	 *  
	 * @return null|object
	 */
	public static function getUniqueClients()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$columns = $db->quoteName('client');
		$query->select('distinct' . $columns);
		$query->from($db->quoteName('#__vendor_client_xref'));
		$db->setQuery($query);
		$rows = $db->loadAssocList();
		$uniqueClient[] = JText::_('JFILTER_PAYOUT_CHOOSE_CLIENT');

		foreach ($rows as $row)
		{
			$uniqueClient[] = array("vendor_client" => $row['client'], "client_value" => $row['client']);
		}

		return $uniqueClient;
	}

	/**
	 * Get array of unique Clients
	 * 
	 * @param   string  $vendor_id  integer
	 * 
	 * @param   string  $client     string
	 * 
	 * @param   string  $currency   string
	 *  
	 * @return null|object
	 */
	public static function getTotalDetails($vendor_id, $client, $currency)
	{
		$com_params = JComponentHelper::getParams('com_tjvendors');
		$payout_day_limit = $com_params->get('payout_limit_days', '0', 'INT');
		$date = JFactory::getDate();
		$payout_date_limit = $date->modify("-" . $payout_day_limit . " day");
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('sum(CASE WHEN ' . $db->quoteName('credit') . ' >= 0 THEN ' . $db->quoteName('credit') . ' ELSE 0 END' . ') As credit');
		$query->select('sum(' . $db->quoteName('debit') . ') As debit');

		$query->from($db->quoteName('#__tjvendors_passbook'));

		if (!empty($vendor_id))
		{
			$query->where($db->quoteName('vendor_id') . '=' . $vendor_id);
		}

		if (!empty($client))
		{
		$query->where($db->quoteName('client') . " = " . $db->quote($client));
		}

		if (!empty($currency))
		{
		$query->where($db->quoteName('currency') . " = " . $db->quote($currency));
		}

		$db->setQuery($query);
		$rows = $db->loadAssoc();
		$totalDebitAmount = $rows['debit'];
		$totalCreditAmount = $rows['credit'];
		$totalpendingAmount = $totalCreditAmount - $totalDebitAmount;

		$totalDetails = array("debitAmount" => $totalDebitAmount, "creditAmount" => $totalCreditAmount, "pendingAmount" => $totalpendingAmount);

		return $totalDetails;
	}

	/**
	 * Get array of unique Clients
	 * 
	 * @param   string  $vendor_id  integer
	 *  
	 * @return clientsForVendor 
	 */
	public static function getClientsForVendor($vendor_id)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*');
		$query->from($db->quoteName('#__vendor_client_xref'));

		if (!empty($vendor_id))
		{
			$query->where($db->quoteName('vendor_id') . ' = ' . $vendor_id);
		}

		$db->setQuery($query);
		$result = $rows = $db->loadAssocList();

		if (!empty($result))
		{
			foreach ($rows as $client)
			{
				$clientsForVendor[] = $client['client'];
			}

			return $clientsForVendor;
		}
	}

	/**
	 * Check For Duplicate users
	 *
	 * @return rows|object
	 */
	public static function checkDuplicateUser()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*');
		$query->from($db->quoteName('#__tjvendors_vendors'));

		if (!empty($user_id))
		{
			$query->where($db->quoteName('user_id') . ' = ' . $user_id);
		}

		$db->setQuery($query);
		$rows = $db->loadAssocList();

		if ($rows)
		{
			return $rows;
		}
	}

	/**
	 * Get paid amount
	 *
	 * @param   string  $vendor_id     integer
	 * 
	 * @param   string  $currency      integer
	 * 
	 * @param   string  $filterClient  client from filter
	 * 
	 * @return amount
	 */
	public static function getPaidAmount($vendor_id,$currency,$filterClient)
	{
		$input = JFactory::getApplication()->input;
		$urlClient = $input->get('client', '', 'STRING');
		$com_params = JComponentHelper::getParams('com_tjvendors');
		$bulkPayoutStatus = $com_params->get('bulk_payout');
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('sum(' . $db->quoteName('debit') . ')');
		$query->from($db->quoteName('#__tjvendors_passbook'));

		if ($filterClient != '0')
		{
			$client = $filterClient;
		}
		else
		{
			$client = $urlClient;
		}

		if (!empty($vendor_id))
		{
			$query->where($db->quoteName('vendor_id') . ' = ' . $db->quote($vendor_id));
		}

		if (!empty($currency))
		{
			$query->where($db->quoteName('currency') . ' = ' . $db->quote($currency));
		}

		$query->where($db->quoteName('credit') . ' != 0');

		if ($bulkPayoutStatus == 0 && !empty($client))
		{
			$query->where($db->quoteName('client') . ' = ' . $db->quote($client));
		}

		$db->setQuery($query);
		$amount = $db->loadresult();

		return $amount;
	}

	/*public static function generatePayoutDetails($vendor_id,$currency,$result,$client)
	{
		$payoutDetails = array("vendor_id" => $vendor_id, "currency" => $currency, "total" => $result, "client" => $client);

		return $payoutDetails;
	}*/

	/**
	 * Get paid amount
	 *
	 * @param   string  $vendor_id  integer
	 * 
	 * @param   string  $currency   currency for that vendor
	 * 
	 * @return amount
	 */
	public static function getTotalPendingAmount($vendor_id,$currency)
	{
		$input = JFactory::getApplication()->input;
		$client = $input->get('client', '', 'STRING');
		$vendor_id = $input->get('vendor_id', '', 'STRING');
		$com_params = JComponentHelper::getParams('com_tjvendors');
		$bulkPayoutStatus = $com_params->get('bulk_payout');
		$db = JFactory::getDbo();
		$subQuery = $db->getQuery(true);
		$clients = self::getClients($vendor_id);
		$totalAmount = 0;

		foreach ($clients as $client)
		{
			$query = $db->getQuery(true);
			$subQuery = $db->getQuery(true);
			$subQuery->select('max(' . $db->quotename('id') . ')');
			$subQuery->from($db->quotename('#__tjvendors_passbook'));

			if (!empty($vendor_id))
			{
				$subQuery->where($db->quotename('vendor_id') . ' = ' . $db->quote($vendor_id));
			}

			if (!empty($currency))
			{
				$subQuery->where($db->quotename('currency') . ' = ' . $db->quote($currency));
			}

			if (!empty($client))
			{
				$subQuery->where($db->quotename('client') . ' = ' . $db->quote($client['client']));
			}

			$query->select($db->quotename('total'));
			$query->from($db->quotename('#__tjvendors_passbook'));
			$query->where($db->quotename('id') . ' = (' . $subQuery . ')');
			$db->setQuery($query);
			$result = $db->loadresult();
			$totalAmount = $totalAmount + $result;
		}

		return $totalAmount;
	}

	/**
	 * Get total amount
	 *
	 * @param   integer  $vendor_id  integer
	 * 
	 * @param   string   $currency   integer
	 * 
	 * @return client|array
	 */

	public static function getTotalAmount($vendor_id, $currency)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$subQuery = $db->getQuery(true);
		$subQuery->select('max(' . $db->quoteName('id') . ')');
		$subQuery->from($db->quoteName('#__tjvendors_passbook'));

		if (!empty($vendor_id))
		{
			$subQuery->where($db->quoteName('vendor_id') . ' = ' . $db->quote($vendor_id));
		}

		if (!empty($currency))
		{
			$subQuery->where($db->quoteName('currency') . ' = ' . $db->quote($currency));
		}

		$query->select('*');
		$query->from($db->quoteName('#__tjvendors_passbook'));
		$query->where($db->quoteName('id') . ' = (' . $subQuery . ')');
		$db->setQuery($query);
		$result = $db->loadAssoc();

		return $result;
	}

	/**
	 * Get array of clients
	 *
	 * @param   integer  $vendor_id  integer
	 * 
	 * @param   string   $currency   integer
	 * 
	 * @param   string   $client     integer
	 * 
	 * @return client|array
	 */
	public static function getPayoutDetail($vendor_id,$currency,$client)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$subQuery = $db->getQuery(true);
		$subQuery->select('max(' . $db->quoteName('id') . ')');
		$subQuery->from($db->quoteName('#__tjvendors_passbook'));

		if (!empty($vendor_id))
		{
			$subQuery->where($db->quoteName('vendor_id') . ' = ' . $db->quote($vendor_id));
		}

		if (!empty($currency))
		{
			$subQuery->where($db->quoteName('currency') . ' = ' . $db->quote($currency));
		}

		if (!empty($client))
		{
			$subQuery->where($db->quoteName('client') . ' = ' . $db->quote($client));
		}

		$query->select('*');
		$query->from($db->quoteName('#__tjvendors_passbook'));
		$query->where($db->quoteName('id') . ' IN (' . $subQuery . ')');
		$db->setQuery($query);
		$payoutDetail = $db->loadAssoc();

		return $payoutDetail;
	}

	/**
	 * Get array of clients
	 *
	 * @param   string  $vendor_id  integer
	 * 
	 * @return client|array
	 */
	public static function getClients($vendor_id)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('distinct' . $db->quoteName('client'));
		$query->from($db->quoteName('#__tjvendors_passbook'));

		if (!empty($vendor_id))
		{
			$query->where($db->quoteName('vendor_id') . ' = ' . $db->quote($vendor_id));
		}

		$db->setQuery($query);
		$clients = $db->loadAssocList();

		return $clients;
	}

	/**
	 * Get get unique Currency
	 *
	 * @param   string  $currency   integer
	 * 
	 * @param   string  $vendor_id  integer
	 *
	 * @param   string  $client     integer
	 * 
	 * @return boolean
	 */

	public static function checkUniqueCurrency($currency, $vendor_id, $client)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->quoteName('currency'));
		$query->from($db->quoteName('#__tjvendors_fee'));

		if (!empty($client))
		{
		$query->where($db->quoteName('vendor_id') . ' = ' . $db->quote($vendor_id));
		}

		if (!empty($client))
		{
			$query->where($db->quoteName('client') . ' = ' . $db->quote($client));
		}

		$db->setQuery($query);
		$currencies = $db->loadAssocList();

		foreach ($currencies as $i)
		{
			if ($currency == $i['currency'])
			{
				return false;
				break;
			}
			else
			{
				continue;
			}
		}

		return true;
	}

	/**
	 * Get get currencies
	 *
	 * @param   string  $vendor_id  integer
	 * 
	 * @return currencies|array
	 */
	public static function getCurrencies($vendor_id)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('DISTINCT' . $db->quoteName('currency'));
		$query->from($db->quoteName('#__tjvendors_passbook'));

		if (!empty($vendor_id))
		{
			$query->where($db->quoteName('vendor_id') . ' = ' . $db->quote($vendor_id));
		}

		$db->setQuery($query);
		$currencies = $db->loadAssocList();

		return $currencies;
	}

	/**
	 * Get get vendor_id
	 *
	 * @param   integer  $vendor_id  integer
	 * 
	 * @param   string   $client     string
	 * 
	 * @param   string   $currency   string
	 * 
	 * @return res|integer
	 */
	public static function getPayableAmount($vendor_id, $client, $currency)
	{
		$com_params = JComponentHelper::getParams('com_tjvendors');
		$payout_day_limit = $com_params->get('payout_limit_days', '0', 'INT');
		$date = JFactory::getDate();
		$payout_date_limit = $date->modify("-" . $payout_day_limit . " day");
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('sum(' . $db->quoteName('credit') . ') As credit');
		$query->from($db->quoteName('#__tjvendors_passbook'));

		if (!empty($vendor_id))
		{
			$query->where($db->quoteName('vendor_id') . ' = ' . $db->quote($vendor_id));
		}

		if (!empty($client))
		{
			$query->where($db->quoteName('client') . ' = ' . $db->quote($client));
		}

		if (!empty($currency))
		{
			$query->where($db->quoteName('currency') . ' = ' . $db->quote($currency));
		}

		$payout_entry = self::checkOrderPayout($vendor_id, $currency);

		if (empty($payout_entry))
		{
			$query->where($db->quoteName('transaction_time') . ' < ' . $db->quote($payout_date_limit));
		}
		else
		{
			$query->where($db->quoteName('transaction_time') . ' > ' . $db->quote($payout_entry['transaction_time']));
			$query->where($db->quoteName('transaction_time') . ' < ' . $db->quote($payout_date_limit));
		}

		$db->setQuery($query);
		$res = $db->loadResult();

		if (!empty($res))
		{
			$result = array("payableAmount" => $res, "payout_date_limit" => $payout_date_limit);
		}
		else
		{
			$result = array("payableAmount" => 0, "payout_date_limit" => $payout_date_limit);
		}

		return $result;
	}

	/**
	 * check order payout
	 *
	 * @param   integer  $vendor_id  integer
	 * 
	 * @param   integer  $currency   integer
	 *
	 * @return res|integer
	 */
	public static function checkOrderPayout($vendor_id, $currency)
	{
		$db = JFactory::getDbo();

		// Create a new query object.
		$query = $db->getQuery(true);
		$query->select('*');
		$query->from($db->quoteName('#__tjvendors_passbook'));
		$query->where($db->quoteName('debit') . ' > 0 AND ' . $db->quoteName('credit') . '!= 0');

		if (!empty($vendor_id))
		{
			$query->where($db->quoteName('vendor_id') . ' = ' . $db->quote($vendor_id));
		}

		if (!empty($currency))
		{
			$query->where($db->quoteName('currency') . ' = ' . $db->quote($currency));
		}

		$db->setQuery($query);
		$result = $db->loadAssoc();

		return $result;
	}

	/**
	 * Get get vendor_id
	 *
	 * @param   string  $userId  integer
	 * 
	 * @return res|integer
	 */
	public static function getUserId($userId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->quoteName('vendor_id'));
		$query->from($db->quoteName('#__tjvendors_vendors'));
		$query->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));
		$db->setQuery($query);
		$res = $db->loadResult();

		return $res;
	}
}
