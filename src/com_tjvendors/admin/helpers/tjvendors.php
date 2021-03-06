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
class TjvendorsHelper
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

					$lang->load($component, JPATH_BASE, null, false, false)
					|| $lang->load($component, JPath::clean(JPATH_ADMINISTRATOR . '/components/' . $component), null, false, false)
					|| $lang->load($component, JPATH_BASE, $lang->getDefault(), false, false)
					|| $lang->load($component, JPath::clean(JPATH_ADMINISTRATOR . '/components/' . $component), $lang->getDefault(), false, false);

					call_user_func(array($cName, 'addSubmenu'), $vName . (isset($section) ? '.' . $section : ''));
				}
			}
		}

		$currentComponent = $input->get('extension', '', 'STRING');

		if ($currentComponent == 'com_tjvendors')
		{
			$notifications  = false;

			switch ($vName)
			{
				case 'notifications':
					$notifications = true;
					break;
			}

			JHtmlSidebar::addEntry(
				JText::_('COM_TJVENDORS_TJNOTIFICATIONS_MENU'), 'index.php?option=com_tjnotifications&extension=com_tjvendors',
				$notifications
			);

			// Load bootsraped filter

			JHtml::_('bootstrap.tooltip');
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
	 * @return boolean|object
	 */
	public static function getUniqueClients()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$columns = $db->quoteName('client');
		$query->select('distinct' . $columns);
		$query->from($db->quoteName('#__vendor_client_xref'));
		$db->setQuery($query);

		try
		{
			$rows = $db->loadAssocList();
		}
		catch (Exception $e)
		{
			JFactory::getApplication()->enqueueMessage(JText::_('COM_TJVENDORS_DB_EXCEPTION_WARNING_MESSAGE'), 'error');
		}

		if (empty($rows))
		{
			return false;
		}

		$uniqueClient   = array();
		$uniqueClient[] = array("vendor_client" => JText::_('JFILTER_PAYOUT_CHOOSE_CLIENT'), "client_value" => '');

		foreach ($rows as $row)
		{
			$tjvendorFrontHelper = new TjvendorFrontHelper;
			$langClient = $tjvendorFrontHelper->getClientName($row['client']);
			$uniqueClient[] = array("vendor_client" => $langClient, "client_value" => $row['client']);
		}

		return $uniqueClient;
	}

	/**
	 * Get total amount
	 *
	 * @param   integer  $vendor_id  integer
	 *
	 * @param   string   $currency   integer
	 *
	 * @param   string   $client     integer
	 *
	 * @return boolean|array
	 */
	public static function getTotalAmount($vendor_id, $currency, $client)
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
		$query->where($db->quoteName('id') . ' = (' . $subQuery . ')');

		$db->setQuery($query);

		try
		{
			$result = $db->loadAssoc();
		}
		catch (Exception $e)
		{
			JFactory::getApplication()->enqueueMessage(JText::_('COM_TJVENDORS_DB_EXCEPTION_WARNING_MESSAGE'), 'error');
		}

		if (empty($result))
		{
			return false;
		}

		return $result;
	}

	/**
	 * Get bulk pending amount
	 *
	 * @param   integer  $vendor_id  integer
	 *
	 * @param   string   $currency   integer
	 *
	 * @return $bulkPendingAmount
	 */
	public static function bulkPendingAmount($vendor_id, $currency)
	{
		JModelLegacy::addIncludePath(JPATH_SITE . '/components/com_tjvendors/models');
		$tjvendorsModelVendor     = JModelLegacy::getInstance('Vendor', 'TjvendorsModel');
		
		$vendorClients = self::getClients($vendor_id);
		$bulkPendingAmount = 0;

		foreach ($vendorClients as $client)
		{
			$pendingAmount = $tjvendorsModelVendor->getPayableAmount($vendor_id, $client['client'], $currency);
			
			if (!empty($pendingAmount))
			{
				$bulkPendingAmount = $bulkPendingAmount + $pendingAmount[$client['client']][$currency];
			}
		}

		return $bulkPendingAmount;
	}

	/**
	 * Get array of clients
	 *
	 * @param   string  $vendor_id  integer
	 *
	 * @return boolean|array
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

		try
		{
			$clients = $db->loadAssocList();
		}
		catch (Exception $e)
		{
			JFactory::getApplication()->enqueueMessage(JText::_('COM_TJVENDORS_DB_EXCEPTION_WARNING_MESSAGE'), 'error');
		}

		if (empty($clients))
		{
			return false;
		}

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

		try
		{
			$currencies = $db->loadAssocList();
		}
		catch (Exception $e)
		{
			JFactory::getApplication()->enqueueMessage(JText::_('COM_TJVENDORS_DB_EXCEPTION_WARNING_MESSAGE'), 'error');
		}

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
	 * @return boolean|array
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

		try
		{
			$currencies = $db->loadAssocList();
		}
		catch (Exception $e)
		{
			JFactory::getApplication()->enqueueMessage(JText::_('COM_TJVENDORS_DB_EXCEPTION_WARNING_MESSAGE'), 'error');
		}

		if (empty($currencies))
		{
			return false;
		}

		return $currencies;
	}

	/**
	 * Get get currencies
	 *
	 * @param   string  $data  integer
	 *
	 * @return object
	 */
	public static function addVendor($data)
	{
		JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tjvendors/models', 'vendor');
		$tjvendorsModelVendors = JModelLegacy::getInstance('Vendor', 'TjvendorsModel');
		JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tjvendors/tables', 'vendor');
		$vendorsDetail = $tjvendorsModelVendors->save($data);
		JTable::addIncludePath(JPATH_ROOT . '/administrator/components/com_tjvendors/tables');
		$table = JTable::getInstance('vendor', 'TJVendorsTable', array());
		$table->load(array('user_id' => $data['user_id']));

		return $table->vendor_id;
	}
}
