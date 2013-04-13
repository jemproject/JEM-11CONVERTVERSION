<?php
/**
 * @version development 
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

if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR); 
defined('_JEXEC') or die;

/**
 * Holds some usefull functions to keep the code a bit cleaner
 *
 * @package JEM
 */
class ELHelper {

	/**
	 * Pulls settings from database and stores in an static object
	 *
	 * @return object
	 * @since 0.9
	 */
static	function &config()
	{
		static $config;

		if (!is_object($config))
		{
			$db 	=  JFactory::getDBO();
			$sql 	= 'SELECT * FROM #__jem_settings WHERE id = 1';
			$db->setQuery($sql);
			$config = $db->loadObject();
			
			$config->params = JComponentHelper::getParams('com_jem');
		}

		return $config;
	}

	/**
   	* Performs dayly scheduled cleanups
   	*
   	* Currently it archives and removes outdated events
   	* and takes care of the recurrence of events
   	*
 	* @since 0.9
   	*/
static	function cleanup($forced = 0)
	{
		$elsettings =  ELHelper::config();
    	$params = JComponentHelper::getParams('com_jem');   
    	$weekstart = $params->get('weekdaystart',0);
    	$anticipation = $params->get('recurrence_anticipation', 30);

		$now 		= time();
		$lastupdate = $elsettings->lastupdate;

		//last update later then 24h?
		//$difference = $now - $lastupdate;

		//if ( $difference > 86400 ) {

		//better: new day since last update?
		$nrdaysnow = floor($now / 86400);
		$nrdaysupdate = floor($lastupdate / 86400);

		if ( $nrdaysnow > $nrdaysupdate || $forced ) {

			$db			=  JFactory::getDBO();

			// get the last event occurence of each recurring published events, with unlimited repeat, or last date not passed.
			$nulldate = '0000-00-00';
			$query = ' SELECT id, CASE recurrence_first_id WHEN 0 THEN id ELSE recurrence_first_id END AS first_id, '
			       . ' recurrence_number, recurrence_type, recurrence_limit_date, recurrence_limit, recurrence_byday, '
			       . ' MAX(dates) as dates, MAX(enddates) as enddates, MAX(recurrence_counter) as counter '
			       . ' FROM #__jem_events '
			       . ' WHERE recurrence_type <> "0" '
			       . ' AND CASE recurrence_limit_date WHEN '.$nulldate.' THEN 1 ELSE NOW() < recurrence_limit_date END '
			       . ' AND recurrence_number <> "0" '
			       . ' AND `published` = 1 '
			       . ' GROUP BY first_id'
			       . ' ORDER BY dates DESC';
			$db->SetQuery( $query );
			$recurrence_array = $db->loadAssocList();
			
			foreach($recurrence_array as $recurrence_row) 
			{
				// get the info of reference event for the duplicates
				$ref_event = & JTable::getInstance('jem_events', '');
				$ref_event->load($recurrence_row['id']);
								
				// get the recurrence information
				$recurrence_number = $recurrence_row['recurrence_number'];
				$recurrence_type = $recurrence_row['recurrence_type'];
				
				// the first day of the week is used for certain rules
				$recurrence_row['weekstart'] = $weekstart;
				
				// calculate next occurence date
				$recurrence_row = ELHelper::calculate_recurrence($recurrence_row);
				
				// add events as long as we are under the interval and under the limit, if specified.				
				while (($recurrence_row['recurrence_limit_date'] == $nulldate || strtotime($recurrence_row['dates']) <= strtotime($recurrence_row['recurrence_limit_date'])) 
				     && strtotime($recurrence_row['dates']) <= time() + 86400*$anticipation) 
				{
					$new_event = & JTable::getInstance('jem_events', '');
					$new_event->bind($ref_event, array('id', 'hits', 'dates', 'enddates'));
					$new_event->recurrence_first_id = $recurrence_row['first_id'];
          			$new_event->recurrence_counter = $recurrence_row['counter'] + 1;
					$new_event->dates = $recurrence_row['dates'];
          			$new_event->enddates = $recurrence_row['enddates'];
          
          			if ($new_event->store())
          			{
          				$recurrence_row['counter']++;
	          			//duplicate categories event relationships
	          			$query = ' INSERT INTO #__jem_cats_event_relations (itemid, catid) '
	                 			. ' SELECT ' . $db->Quote($new_event->id) . ', catid FROM #__jem_cats_event_relations WHERE itemid = ' . $db->Quote($ref_event->id);
	          			$db->setQuery($query);
	          			if (!$db->query()) {
	          				echo JText::_('Error saving categories for event "' . $ref_event->title . '" new recurrences\n');
	          			}
          			}
          
          			$recurrence_row = ELHelper::calculate_recurrence($recurrence_row);
				}
			}

			//delete outdated events
			if ($elsettings->oldevent == 1) {
				$query = 'DELETE FROM #__jem_events WHERE dates > 0  AND DATE_SUB(NOW(), INTERVAL '.$elsettings->minus.' DAY) > (IF (enddates <> '.$nulldate.', enddates, dates))';
				$db->SetQuery( $query );
				$db->Query();
			}

			//Set state archived of outdated events
			if ($elsettings->oldevent == 2) {
				$query = 'UPDATE #__jem_events SET published = -1 WHERE dates > 0 AND DATE_SUB(NOW(), INTERVAL '.$elsettings->minus.' DAY) > (IF (enddates <> '.$nulldate.', enddates, dates)) AND published = 1';
				$db->SetQuery( $query );
				$db->Query();
			}
			
			//Set timestamp of last cleanup
			$query = 'UPDATE #__jem_settings SET lastupdate = '.time().' WHERE id = 1';
			$db->SetQuery( $query );
			$db->Query();
		}
	}

	/**
	 * this methode calculate the next date
	 */
static	function calculate_recurrence($recurrence_row) 
	{
		// get the recurrence information
		$recurrence_number = $recurrence_row['recurrence_number'];
		$recurrence_type = $recurrence_row['recurrence_type'];

		$day_time = 86400;	// 60 sec. * 60 min. * 24 h
		$week_time = 604800;// $day_time * 7days
		$date_array = ELHelper::generate_date($recurrence_row['dates'], $recurrence_row['enddates']);

		switch($recurrence_type) {
			case "1":
				// +1 hour for the Summer to Winter clock change
				$start_day = mktime(1,0,0,$date_array["month"],$date_array["day"],$date_array["year"]);
				$start_day = $start_day + ($recurrence_number * $day_time);
				break;
			case "2":
				// +1 hour for the Summer to Winter clock change
				$start_day = mktime(1,0,0,$date_array["month"],$date_array["day"],$date_array["year"]);
				$start_day = $start_day + ($recurrence_number * $week_time);
				break;
			case "3": // month recurrence
			  /*
			   * warning here, we have to make sure the date exists: 31 of october + 1 month = 31 of november, which doesn't exists => skip the date!
			   */
				$start_day = mktime(1,0,0,($date_array["month"] + $recurrence_number),$date_array["day"],$date_array["year"]);

        $i = 1;        
        while ( date('d', $start_day) !=  $date_array["day"] && $i < 20 ) { // not the same day of the month... try next date !
        	$i++;
          $start_day = mktime(1,0,0,($date_array["month"] + $recurrence_number*$i),$date_array["day"],$date_array["year"]);
        }
				break;
			case "4":
        		$selected = ELHelper::convert2CharsDaysToInt(explode(',', $recurrence_row['recurrence_byday']), $recurrence_row['weekstart']);  // the selected weekdays
        		$current_weekday = (int) $date_array["weekday"];
				
        		if ($recurrence_row['weekstart'] == 1) {
        			// O for monday, not sunday
        			$current_weekday = ($current_weekday + 6) % 7;
        		}
        
        		if (count($selected) == 0) {
        			// this shouldn't happen, but if it does, to prevent problem use the current weekday for the repetition.
          			JError::raiseWarning(500, JText::_( 'Empty weekday recurrence' ) );
          			$selected = array($current_weekday);
        		}
		
        		sort($selected);
				
				if ($recurrence_number < 5) {
					// 1. - 4. week in a month
					// look for the position of current start date in 'selected' days.
					if ($current_weekday >= max($selected)) {
						
						// last selected day of the week => next occurence is first selected day of next (interval) week
            			$start_day = $date_array["unixtime"] - ($current_weekday - $selected[0]) * $day_time + $week_time * $recurrence_number;
					
					} else{
						
						// next weekday
						foreach($selected as $k => $day)
	          			{
	            			if ($current_weekday < $day) {
                				$next_weekday = $day;
                			break;
	            			}
	          			}
						$start_day = $date_array["unixtime"] + ($next_weekday - $current_weekday) * $day_time;
					}
				} else {					
         			if ($current_weekday >= max($selected)) {
            			// next occurence is next month
          				// the last or the before last week in a month
            			// the last day in the new month
	         			$start_day = mktime(1,0,0,($date_array["month"] + 2),1,$date_array["year"]) - $day_time;
	          			$weekday_is = date("w",$start_day);
	          
	          			// first selected day of the week
	          			$weekday_must = $selected[0];
	          
	          			// calculate the day difference between these days
	          			if ($weekday_is >= $weekday_must) {
	            			$day_diff = $weekday_is - $weekday_must;
	          			} else {
	            			$day_diff = ($weekday_is - $weekday_must) + 7;
	          			}
						
	          			$start_day = ($start_day - ($day_diff * $day_time));
						
	          			if ($recurrence_number == 6) {  // before last?
	            			$start_day = $start_day - $week_time;
	          			}
          			} else {
          				// next weekday
            			foreach($selected as $k => $day)
            			{
              				if ($current_weekday < $day) {
                				$next_weekday = $day;
                			break;
              				}
            			}
            			// next occurence is next selected weekday
            			$start_day = $date_array["unixtime"] + ($next_weekday - $current_weekday) * $day_time;          	
          			}
				}
				break;
		}
		
		$recurrence_row['dates'] = date("Y-m-d", $start_day);
		
		if ($recurrence_row['enddates']) {
			$recurrence_row['enddates'] = date("Y-m-d", $start_day + $date_array["day_diff"]);
		}
		
		if ($start_day < $date_array["unixtime"]) {
			JError::raiseError(500, JText::_( 'Recurrence date generation error' ) );
		}
		
		return $recurrence_row;
	}

	/**
	 * this method generate the date string to a date array
	 *
	 * @var string the date string
	 * @return array the date informations
	 * @access public
	 */
static	function generate_date($startdate, $enddate) {
		$startdate = explode("-",$startdate);
		$date_array = array("year" => $startdate[0],
							"month" => $startdate[1],
							"day" => $startdate[2],
							"weekday" => date("w",mktime(1,0,0,$startdate[1],$startdate[2],$startdate[0])),
		          "unixtime" => mktime(1,0,0,$startdate[1],$startdate[2],$startdate[0]));
		if ($enddate) {
			$enddate = explode("-", $enddate);
			$day_diff = (mktime(1,0,0,$enddate[1],$enddate[2],$enddate[0]) - mktime(1,0,0,$startdate[1],$startdate[2],$startdate[0]));
			$date_array["day_diff"] = $day_diff;
		}
		return $date_array;
	}
	
	/**
	 * return day number of the week starting with 0 for first weekday
	 *
	 * @param array of 2 letters day
	 * @return array of int
	 */
static	function convert2CharsDaysToInt($days, $firstday = 0)
	{
		$result = array();
		foreach ($days as $day)
		{
			if ($firstday == 0) // sunday
			{
				switch (strtoupper($day))
				{
					case 'SU':
						$result[] = 0;
						break;
		      		case 'MO':
	          			$result[] = 1;
	          			break;
		      		case 'TU':
	         			$result[] = 2;
	          			break;
		      		case 'WE':
	          			$result[] = 3;
	         			break;
		      		case 'TH':
	          			$result[] = 4;
	          			break;
		      		case 'FR':
	          			$result[] = 5;
	          			break;
		      		case 'SA':
	          			$result[] = 6;
	          			break;
		      		default:
		        		JError::raiseWarning(500, JText::_( 'Wrong ical day string' ) );
				}
			} else {
				
				//monday
        		switch (strtoupper($day))
        		{
          			case 'MO':
            			$result[] = 0;
            			break;
          			case 'TU':
            			$result[] = 1;
            			break;
          			case 'WE':
            			$result[] = 2;
            			break;
          			case 'TH':
            			$result[] = 3;
            			break;
          			case 'FR':
            			$result[] = 4;
            			break;
          			case 'SA':
            			$result[] = 5;
            			break;
          			case 'SU':
            			$result[] = 6;
            			break;
          			default:
            			JError::raiseWarning(500, JText::_( 'Wrong ical day string' ) );
        		}
      		}
		}
		
		return $result;
	}
	
	/**
	 * transforms <br /> and <br> back to \r\n
	 *
	 * @param string $string
	 * @return string
	 */
static	function br2break($string)
	{
		return preg_replace("=<br(>|([\s/][^>]*)>)\r?\n?=i", "\r\n", $string);
	}

	/**
	 * use only some importent keys of the jem_events - database table for the where query
	 *
	 * @param string $key
	 * @return boolean
	 */
static	function where_table_rows($key) {
		if ($key == 'locid' ||
			//$key == 'catsid' ||
			$key == 'dates' ||
			$key == 'enddates' ||
			$key == 'times' ||
			$key == 'endtimes' ||
			$key == 'alias' ||
			$key == 'created_by') {
			return true;
		} else {
			return false;
		}
	}
	
static	function buildtimeselect($max, $name, $selected, $class = 'class="inputbox"')
	{
		$timelist 	= array();

		foreach(range(0, $max) as $wert) {
		    if(strlen($wert) == 2) {
				$timelist[] = JHTML::_( 'select.option', $wert, $wert);
    		}else{
      			$timelist[] = JHTML::_( 'select.option', '0'.$wert, '0'.$wert);
    		}
		}
		return JHTML::_('select.genericlist', $timelist, $name, $class, 'value', 'text', $selected );
	}
 
	/**
	 * return country options from the database
	 *
	 * @return unknown
	 */
static	function getCountryOptions()
	{
		$db   =  JFactory::getDBO();
    	$sql  = 'SELECT iso2 AS value, name AS text FROM #__jem_countries ORDER BY name';
    	$db->setQuery($sql);
    	
		return $db->loadObjectList();
  	}
  	

	/**
	* Build the select list for access level
	*/
static	function getAccesslevelOptions()
	{
		$db = JFactory::getDBO();

		$query = 'SELECT id AS value, title AS text'
		. ' FROM #__viewlevels'
		. ' ORDER BY id'
		;
		$db->setQuery( $query );
		$groups = $db->loadObjectList();

		return $groups;
	}
	
	/**
	 * returns mime type of a file
	 * 
	 * @param string file path
	 * @return string mime type
	 */
static	function getMimeType($filename)
	{
		if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
    }
		else if (function_exists('mime_content_type') && 0) 
		{
			return mime_content_type($filename);
		}
		else 
		{
			$mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

			//$ext = strtolower(array_pop(explode('.',$filename)));
			$var = explode('.',$filename);
			$ext = strtolower(array_pop($var));
			if (array_key_exists($ext, $mime_types)) {
				return $mime_types[$ext];
			}
			else {
				return 'application/octet-stream';
			}
		}
	}
	

	/**
	 * updates waiting list of specified event
	 * 
	 * @param int event id
	 * @param boolean bump users off/to waiting list
	 * @return bool
	 */
static	function updateWaitingList($event)
	{
    $db = &Jfactory::getDBO();
    
		// get event details for registration
    $query = ' SELECT maxplaces, waitinglist FROM #__jem_events WHERE id = ' . $db->Quote($event);
    $db->setQuery($query);
    $event_places = $db->loadObject();    
			
		// get attendees after deletion, and their status
		$query = 'SELECT r.id, r.waiting '
				. ' FROM #__jem_register AS r'
				. ' WHERE r.event = '.$db->Quote($event)
				. ' ORDER BY r.uregdate ASC '
				;
		$db->SetQuery( $query );
		$res = $db->loadObjectList();
			
		$registered = 0;
		$waiting    = array();
		foreach ((array) $res as $r)
		{				
			if ($r->waiting) {
				$waiting[] = $r->id;
			}
			else {				
				$registered++;
			}
		}
			
		if ($registered < $event_places->maxplaces && count($waiting))
		{
			// need to bump users to attending status
			$bumping = array_slice($waiting, 0, $event_places->maxplaces - $registered);
			$query = ' UPDATE #__jem_register SET waiting = 0 WHERE id IN ('.implode(',', $bumping).')';
			$db->setQuery($query);
			if (!$db->query()) {
				$this->setError(JText::_('Failed bumping users from waiting to confirmed list'));
				Jerror::raisewarning(0, JText::_('Failed bumping users from waiting to confirmed list').': '.$db->getErrorMsg());
			}
			else
			{
				$booked = $registered + count($bumping);
				foreach ($bumping AS $register_id)
				{		
					JPluginHelper::importPlugin( 'jem' );
			    $dispatcher =& JDispatcher::getInstance();
			   	$res = $dispatcher->trigger( 'onUserOnOffWaitinglist', array( $register_id ) );
				}
			}
		}

		return true;
	}

  /**
   * returns array of timezones indexed by offset
   * 
   * @return array
   */
static  function getTimeZones()
  {
  	$timezones = array(
        '-12'=>'Pacific/Kwajalein',
        '-11'=>'Pacific/Samoa',
        '-10'=>'Pacific/Honolulu',
        '-9'=>'America/Juneau',
        '-8'=>'America/Los_Angeles',
        '-7'=>'America/Denver',
        '-6'=>'America/Mexico_City',
        '-5'=>'America/New_York',
        '-4'=>'America/Caracas',
        '-3.5'=>'America/St_Johns',
        '-3'=>'America/Argentina/Buenos_Aires',
        '-2'=>'Atlantic/Azores',// no cities here so just picking an hour ahead
        '-1'=>'Atlantic/Azores',
        '0'=>'Europe/London',
        '1'=>'Europe/Paris',
        '2'=>'Europe/Helsinki',
        '3'=>'Europe/Moscow',
        '3.5'=>'Asia/Tehran',
        '4'=>'Asia/Baku',
        '4.5'=>'Asia/Kabul',
        '5'=>'Asia/Karachi',
        '5.5'=>'Asia/Calcutta',
        '6'=>'Asia/Colombo',
        '7'=>'Asia/Bangkok',
        '8'=>'Asia/Singapore',
        '9'=>'Asia/Tokyo',
        '9.5'=>'Australia/Darwin',
        '10'=>'Pacific/Guam',
        '11'=>'Asia/Magadan',
        '12'=>'Asia/Kamchatka'
    ); 
    return $timezones;
  }
  
  /**
   * returns timezone name from offset
   * @param string $offset
   * @return string
   */
static  function getTimeZone($offset)
  {
  	$tz = self::getTimeZones();
  	if (isset($tz[$offset])) {
  		return $tz[$offset];
  	}
  	return false;
  }

	/**
	 * return initialized calendar tool class for ics export
	 * 
	 * @return object
	 */
static	function getCalendarTool()
	{
		require_once JPATH_SITE.DS.'components'.DS.'com_jem'.DS.'classes'.DS.'iCalcreator.class.php';
		$mainframe = JFactory::getApplication();
    
		$offset = (float) $mainframe->getCfg('offset');
		$timezone_name = ELHelper::getTimeZone($offset);
								
		$vcal = new vcalendar();                          // initiate new CALENDAR
		if (!file_exists(JPATH_SITE.DS.'cache'.DS.'com_jem')) {
			jimport('joomla.filesystem.folder');
			JFolder::create(JPATH_SITE.DS.'cache'.DS.'com_jem');
		}
		$vcal->setConfig('directory', JPATH_SITE.DS.'cache'.DS.'com_jem');
	//	$vcal->setProperty('unique_id', 'events@'.$mainframe->getCfg('sitename'));
		$vcal->setProperty( "calscale", "GREGORIAN" ); 
    $vcal->setProperty( 'method', 'PUBLISH' );
    if ($timezone_name) {
    	$vcal->setProperty( "X-WR-TIMEZONE", $timezone_name ); 
    }
    return $vcal;
	}
	
static	function icalAddEvent(&$calendartool, $event)
	{
		require_once JPATH_SITE.DS.'components'.DS.'com_jem'.DS.'classes'.DS.'iCalcreator.class.php';
		$mainframe = JFactory::getApplication();
		$params = $mainframe->getParams('com_jem');
		
		$offset = (float) $mainframe->getCfg('offset');
		$timezone_name = ELHelper::getTimeZone($offset);
//		$hours = ($offset >= 0) ? floor($offset) : ceil($offset);
//		$mins = abs($offset - $hours) * 60;
//		$utcoffset = sprintf('%+03d%02d00', $hours, $mins);
		
		// get categories names
		$categories = array();
		foreach ($event->categories as $c) {
			$categories[] = $c->catname;
		}
		
		if (!$event->dates || $event->dates == '0000-00-00') {
			// no start date...
			return false;
		}		
		// make end date same as start date if not set
		if (!$event->enddates || $event->enddates == '0000-00-00') {
			$event->enddates = $event->dates;
		}
		
		// start
		if (!preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/',$event->dates, $start_date)) {
			JError::raiseError(0, JText::_('COM_JEM_ICAL_EXPORT_WRONG_STARTDATE_FORMAT'));
		}
		$date = array('year' => (int) $start_date[1], 'month' => (int) $start_date[2], 'day' => (int) $start_date[3]);
			
		// all day event if start time is not set
		if ( !$event->times || $event->times == '00:00:00' ) // all day !
		{
			$dateparam = array('VALUE' => 'DATE');
					
			// for ical all day events, dtend must be send to the next day
			$event->enddates = strftime('%Y-%m-%d', strtotime($event->enddates.' +1 day'));
			
			if (!preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/',$event->enddates, $end_date)) {
				JError::raiseError(0, JText::_('COM_JEM_ICAL_EXPORT_WRONG_ENDDATE_FORMAT'));
			}
			$date_end = array('year' => $end_date[1], 'month' => $end_date[2], 'day' => $end_date[3]);
			$dateendparam = array('VALUE' => 'DATE');			
		} 
		else // not all day events, there is a start time
		{		
			if (!preg_match('/([0-9]{2}):([0-9]{2}):([0-9]{2})/',$event->times, $start_time)) {
				JError::raiseError(0, JText::_('COM_JEM_ICAL_EXPORT_WRONG_STARTTIME_FORMAT'));
			}
			$date['hour'] = $start_time[1];
			$date['min']  = $start_time[2];
			$date['sec']  = $start_time[3];
			$dateparam = array('VALUE' => 'DATE-TIME');
			if ($params->get('ical_tz', 1)) {
				$dateparam['TZID'] = $timezone_name;
			}
			
			if ( !$event->endtimes || $event->endtimes == '00:00:00' )
			{
				$event->endtimes = $event->times;
			}
			
			// if same day but end time < start time, change end date to +1 day
			if ($event->enddates == $event->dates && strtotime($event->dates.' '.$event->endtimes) < strtotime($event->dates.' '.$event->times)) {
				$event->enddates = strftime('%Y-%m-%d', strtotime($event->enddates.' +1 day'));
			}

			if (!preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/',$event->enddates, $end_date)) {
				JError::raiseError(0, JText::_('COM_JEM_ICAL_EXPORT_WRONG_ENDDATE_FORMAT'));
			}
			$date_end = array('year' => $end_date[1], 'month' => $end_date[2], 'day' => $end_date[3]);
						
			if (!preg_match('/([0-9]{2}):([0-9]{2}):([0-9]{2})/',$event->endtimes, $end_time)) {
				JError::raiseError(0, JText::_('COM_JEM_ICAL_EXPORT_WRONG_STARTTIME_FORMAT'));
			}
			$date_end['hour'] = $end_time[1];
			$date_end['min']  = $end_time[2];
			$date_end['sec']  = $end_time[3];
			$dateendparam = array('VALUE' => 'DATE-TIME');	
			if ($params->get('ical_tz', 1)) {
				$dateendparam['TZID'] = $timezone_name;
			}	
		}

		// item description text
		$description = $event->title.'\\n';
		$description .= JText::_( 'CATEGORY' ).': '.implode(', ', $categories).'\\n';

		// url link to event
		
		//Original link 
		//$link = JURI::base().JEMHelperRoute::getRoute($event->slug);
		
		$app =  JFactory::getApplication();
		$menuitem = $app->getMenu()->getActive()->id;
		$link = JURI::base().JEMHelperRoute::getRoute($event->slug).'&Itemid='.$menuitem;
		
		
		
		$link = JRoute::_( $link );
		$description .= JText::_( 'COM_JEM_ICS_LINK' ).': '.$link.'\\n';
		
		// location
		$location = array($event->venue);
		if (isset($event->street) && !empty($event->street)) {
			$location[] = $event->street;
		}
		if (isset($event->city) && !empty($event->city)) {
			$location[] = $event->city;
		}
		if (isset($event->countryname) && !empty($event->countryname)) {
			$exp = explode(",",$event->countryname);
			$location[] = $exp[0];
		}
		$location = implode(",", $location);
			
		$e = new vevent();              // initiate a new EVENT
		$e->setProperty( 'summary', $event->title );           // title
		$e->setProperty( 'categories', implode(', ', $categories) );           // categorize
		$e->setProperty( 'dtstart', $date, $dateparam );
		if (count($date_end)) {
			$e->setProperty( 'dtend', $date_end, $dateendparam );
		}
		$e->setProperty( 'description', $description );    // describe the event
		$e->setProperty( 'location', $location ); // locate the event
		$e->setProperty( 'url', $link );
		$e->setProperty( 'uid', 'event'.$event->id.'@'.$mainframe->getCfg('sitename') );
		$calendartool->addComponent( $e );                    // add component to calendar
		return true;
	}
	
	/**
	 * return true is a date is valid (not null, or 0000-00...)
	 * 
	 * @param string $date
	 * @return boolean
	 */
static	function isValidDate($date)
	{
		if (is_null($date)) {
			return false;
		}
		if ($date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
			return false;
		}
		if (!strtotime($date)) {
			return false;
		}
		return true;		
	}
}
?>