<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// no direct access

if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined( '_JEXEC' ) or die;

require_once JPATH_SITE.DS.'components'.DS.'com_jem'.DS.'classes'.DS.'iCalcreator.class.php';

jimport( 'joomla.application.component.view');

/**
 * ICS events list View class of the JEM component
 *
 * @package JEM
 * @since 1.1
 */
class JEMViewEventslist extends JViewLegacy
{
	/**
	 * Creates the output for the details view
	 *
 	 * @since 2.0
	 */
	function display($tpl = null)
	{		
		$mainframe = JFactory::getApplication();
    
		$offset = (float) $mainframe->getCfg('offset');
		$timezone_name = ELHelper::getTimeZone($offset);
		$hours = ($offset >= 0) ? floor($offset) : ceil($offset);
		$mins = abs($offset - $hours) * 60;
		$utcoffset = sprintf('%+03d%02d00', $hours, $mins);
		
		$settings = ELHelper::config();
		
		// Get data from the model
		$model = $this->getModel();
		$model->setLimit($settings->params->get('ical_max_items', 100));
		$model->setLimitstart(0);
		$rows =  $model->getData();
				
    // initiate new CALENDAR
		$vcal = ELHelper::getCalendarTool();
	//	$vcal->setProperty('unique_id', 'allevents@'.$mainframe->getCfg('sitename'));
		$vcal->setConfig( "filename", "events.ics" );
		
		foreach ( $rows as $row )
		{				
			ELHelper::icalAddEvent($vcal, $row);	
		}
		$vcal->returnCalendar();                       // generate and redirect output to user browser
		echo $vcal->createCalendar(); // debug
	}
}
?>