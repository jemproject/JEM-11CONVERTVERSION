<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

/**
 * Based on
 * @class: activeCalendar
 * @project: Active Calendar
 * @version: 1.2.0;
 * @author: Giorgos Tsiledakis;
 * @date: 23 Feb 2006;
 * @copyright: Giorgos Tsiledakis;
 * @license: GNU LESSER GENERAL PUBLIC LICENSE;
 * Support, feature requests and bug reports please at : http://www.micronetwork.de/activecalendar/
 * Special thanks to Corissia S.A (http://www.corissia.com) for the permission to publish the source code
 * Thanks to Maik Lindner (http://nifox.com) for his help developing this class
 */
defined('_JEXEC') or die;

class JemCalendar
{
	/*
	----------------------
	@START CONFIGURATION
	----------------------
	*/
	/*
	********************************************************************************
	You can change below the month and day names, according to your language
	This is just the default configuration. You may set the month and day names by calling setMonthNames() and setDayNames()
	********************************************************************************
	*/
	public $jan="January";
	public $feb="February";
	public $mar="March";
	public $apr="April";
	public $may="May";
	public $jun="June";
	public $jul="July";
	public $aug="August";
	public $sep="September";
	public $oct="October";
	public $nov="November";
	public $dec="December";
	public $sun="Sun";
	public $mon="Mon";
	public $tue="Tue";
	public $wed="Wed";
	public $thu="Thu";
	public $fri="Fri";
	public $sat="Sat";
	/*
	********************************************************************************
	You can change below the default year's and month's view navigation controls
	********************************************************************************
	*/
	public $yearNavBack=" &lt;&lt; "; // Previous year, this could be an image link
	public $yearNavForw=" &gt;&gt; "; // Next year, this could be an image link
	public $monthNavBack=" &lt;&lt; "; // Previous month, this could be an image link
	public $monthNavForw=" &gt;&gt; "; // Next month, this could be an image link
	public $selBtn="Go"; // value of the date picker button (if enabled)
	public $monthYearDivider=" "; // the divider between month and year in the month`s title
	/*
	********************************************************************************
	$startOnSun = false: first day of week is Monday
	$startOnSun = true: first day of week is Sunday
	You may use the method setFirstWeekDay() instead
	********************************************************************************
	*/
	public $startOnSun=false;
	/*
	********************************************************************************
	$rowCount : defines the number of months in a row in yearview (can be also set by the method showYear())
	********************************************************************************
	*/
	public $rowCount=4;
	/*
	********************************************************************************
	Names of the generated html classes. You may change them to avoid any conflicts with your existing CSS
	********************************************************************************
	*/
	public $cssYearTable="year"; // table tag: calendar year
	public $cssYearTitle="yearname"; // td tag: calendar year title
	public $cssYearNav="yearnavigation"; // td tag: calendar year navigation
	public $cssMonthTable="month"; // table tag: calendar month
	public $cssMonthTitle="monthname"; // td tag: calendar month title
	public $cssMonthNav="monthnavigation"; // td tag: calendar month navigation
	public $cssWeekDay="dayname"; // td tag: calendar weekdays
	public $cssWeekNumTitle="weeknumtitle"; // td tag: title of the week numbers
	public $cssWeekNum="weeknum"; // td tag: week numbers
	public $cssPicker="datepicker"; // td tag: date picker
	public $cssPickerForm="datepickerform"; // form tag: date picker form
	public $cssPickerMonth="monthpicker"; // select tag: month picker
	public $cssPickerYear="yearpicker"; // select tag: year picker
	public $cssPickerButton="pickerbutton"; // input (submit) tag: date picker button
	public $cssMonthDay="monthday"; // td tag: days, that belong to the current month
	public $cssNoMonthDay="nomonthday"; // td tag: days, that do not belong to the current month
	public $cssToday="today"; // td tag: the current day
	public $cssSelecDay="selectedday"; // td tag: the selected day
	public $cssSunday="sunday"; // td tag: all Sundays (can be disabled, see below)
	public $cssSaturday="saturday"; // td tag: all Saturdays (can be disabled, see below)
	public $cssEvent="event"; // td tag: event day set by setEvent(). Multiple class names can be generated
	public $cssPrefixSelecEvent="selected"; // prefix for the event class name if the event is selected
	public $cssPrefixTodayEvent="today"; // prefix for the event class name if the event is the current day
	public $cssEventContent="eventcontent"; // table tag: calendar event content. Multiple class names can be generated
	public $crSunClass=true; // true: creates a td class on every Sunday (set above)
	public $crSatClass=true; // true: creates a td class on every Saturday (set above)
	/*
	********************************************************************************
	You can change below the GET VARS NAMES [url parameter names] (navigation + day links)
	You should modify the private method mkUrl() or mkWeekNum(), if you want to change the structure of the generated links
	********************************************************************************
	*/
	public $yearID="yearID";
	public $monthID="monthID";
	public $dayID="dayID";
	public $weekID="weekID";
	/*
	********************************************************************************
	Default start and end year for the date picker (can be changed, if using the ADOdb Date Library)
	********************************************************************************
	*/
	public $startYear=1971;
	public $endYear=2037;
	/*
	----------------------
	@START PUBLIC METHODS
	----------------------
	*/

	/**
     * PUBLIC activeCalendar() -> class constructor, does the initial date calculation
     * @param $GMTDiff: GMT Zone for current day calculation, do not set to use local server time
     *
     */
	public function __construct($year=false,$month=false,$day=false,$GMTDiff="none") {
		$this->timetoday=time();
		$this->selectedday=-2;
		$this->selectedyear=$year;
		$this->selectedmonth=$month;
		if (!$month) {
			$month=1;
		}
		if (!$day) {
			$day=1;
		}
		else {
			$this->selectedday=$day;
		}
		$h=$this->mkActiveGMDate("H");
		$m=$this->mkActiveGMDate("i");
		$s=$this->mkActiveGMDate("s");
		$d=$this->mkActiveGMDate("j");
		$mo=$this->mkActiveGMDate("n");
		$y=$this->mkActiveGMDate("Y");
		$is_dst=$this->mkActiveDate("I");
		if ($GMTDiff!="none") {
			$this->timetoday=$this->mkActiveTime($h,$m,$s,$mo,$d,$y)+(3600*($GMTDiff+$is_dst));
		}
		$this->unixtime=$this->mkActiveTime($h,$m,$s,$month,$day,$year);
		if ($this->unixtime==-1 || !$year) {
			$this->unixtime=$this->timetoday;
		}
		$this->daytoday=$this->mkActiveDate("j");
		$this->monthtoday=$this->mkActiveDate("n");
		$this->yeartoday=$this->mkActiveDate("Y");
		if (!$day) {
			$this->actday=$this->daytoday;
		}
		else {
			$this->actday=$this->mkActiveDate("j",$this->unixtime);
		}
		if (!$month) {
			$this->actmonth=$this->monthtoday;
		}
		else {
			$this->actmonth=$this->mkActiveDate("n",$this->unixtime);
		}
		if (!$year) {
			$this->actyear=$this->yeartoday;
		}
		else {
			$this->actyear=$this->mkActiveDate("Y",$this->unixtime);
		}
		$this->has31days=checkdate($this->actmonth,31,$this->actyear);
		$this->isSchalt=checkdate(2,29,$this->actyear);
		if ($this->isSchalt==1 && $this->actmonth==2) {
			$this->maxdays=29;
		}
		elseif ($this->isSchalt!=1 && $this->actmonth==2) {
			$this->maxdays=28;
		}
		elseif ($this->has31days==1) {
			$this->maxdays=31;
		}
		else {
			$this->maxdays=30;
		}
		$this->firstday=$this->mkActiveDate("w", $this->mkActiveTime(0,0,1,$this->actmonth,1,$this->actyear));
		$this->GMTDiff=$GMTDiff;
	}

	/**
     *
     * enableYearNav() -> enables the year's navigation controls
     *
     */
	public function enableYearNav($link=false,$arrowBack=false,$arrowForw=false) {
		if ($link) {
			$this->urlNav=$link;
		}
		if ($arrowBack) {
			$this->yearNavBack=$arrowBack;
		}
		if ($arrowForw) {
			$this->yearNavForw=$arrowForw;
		}
		$this->yearNav=true;
	}

	/**
     *
     * enableMonthNav() -> enables the month's navigation controls
     *
     */
	public function enableMonthNav($link=false,$arrowBack=false,$arrowForw=false) {
		if ($link) {
			$this->urlNav=$link;
		}
		if ($arrowBack) {
			$this->monthNavBack=$arrowBack;
		}
		if ($arrowForw) {
			$this->monthNavForw=$arrowForw;
		}
		$this->monthNav=true;
	}

	/**
     *
     * enableDayLinks() -> enables the day links
     * @param $javaScript: sets a Javascript function on each day link
     *
     */
	public function enableDayLinks($link=false,$javaScript=false) {
		if ($link) {
			$this->url=$link;
		}
		if ($javaScript) {
			$this->javaScriptDay=$javaScript;
		}
		$this->dayLinks=true;
	}

	/**
     *
     * enableNewEventLinks() -> enables links on each day to create new events
     * @param $link: full html template with token "{date}" to get day's date
     *
     */
	public function enableNewEventLinks($link) {
		if ($link && (stripos($link, '{date}') !== false)) {
			$this->htmlNewEventLink = $link;
			$this->dayNewEventLinks = true;
		} else {
			$this->dayNewEventLinks = false;
		}

		return $this->dayNewEventLinks;
	}

	/**
     *
     * enableDatePicker() -> enables the day picker control
     *
     */
	public function enableDatePicker($startYear=false,$endYear=false,$link=false,$button=false) {
		if ($link) {
			$this->urlPicker=$link;
		}
		if ($startYear && $endYear) {
			if ($startYear>=$this->startYear && $startYear<$this->endYear) $this->startYear=$startYear;
			if ($endYear>$this->startYear && $endYear<=$this->endYear) $this->endYear=$endYear;
		}
		if ($button) $this->selBtn=$button;
		$this->datePicker=true;
	}

	/**
     *
     * enableWeekNum() -> enables a week number column
     *
     */
	public function enableWeekNum($title="",$link=false,$javaScript=false) {
		// checking before enabling, as week number calulation works only if php version > 4.1.0 [php function: date ("W")]
		if (is_integer($this->getWeekNum($this->actday))) {
			$this->weekNum=true;
			$this->weekNumTitle=$title;
			$this->monthSpan++;

			if (empty($link)) {
				$this->weekUrl='';
			} else {
				$this->weekUrl=$link;
			}

			if (empty($javaScript)) {
				$this->javaScriptWeek='';
			} else {
				$this->javaScriptWeek=$javaScript;
			}
		}
	}

	/**
     *
     * setEvent() -> sets a calendar event, $id: the HTML class (css layout)
     *
     */
	public function setEvent($year,$month,$day,$id=false,$url=false) {
		$eventTime=$this->mkActiveTime(0,0,1,$month,$day,$year);
		if (!$id) {
			$id=$this->cssEvent;
		}
		$this->calEvents[$eventTime]=$id;
		$this->calEventsUrl[$eventTime]=$url;
	}

    /**
     *
     * setEventContent() -> sets a calendar event content,
     *
     * set event content
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @param string | array $content can be a string or an array, $id: the HTML class (css layout)
     * @param string $url
     * @param string $id the HTML class
     */
    public function setEventContent($year, $month, $day, $content, $url=false, $id=false)
    {
        // event time
        $eventTime = $this->mkActiveTime(0, 0, 1, $month, $day, $year);

        // add to eventcontent array
        $eventContent[$eventTime] = $content;
        $this->calEventContent[] = $eventContent;

        // add specific id
        if (!$id) {
            $id = $this->cssEventContent;
        }
        $this->calEventContentId[] = $id;

        // add url
        if ($url) {
            $this->calEventContentUrl[] = $url;
        }
        else $this->calEventContentUrl[] = $this->calInit++;
    }

    /**
     *
     *setMonthNames() -> sets the month names, $namesArray must be an array of 12 months starting with January
     *
	*/
	public function setMonthNames($namesArray) {
		if (!is_array($namesArray) || count($namesArray)!=12) {
			return false;
		}
		else {
			$this->monthNames=$namesArray;
		}
	}

    /**
     *
     *setDayNames() -> sets the week day names, $namesArray must be an array of 7 days starting with Sunday
     *
	*/
	public function setDayNames($namesArray) {
		if (!is_array($namesArray) || count($namesArray)!=7) {
			return false;
		}
		else {
			$this->dayNames=$namesArray;
		}
	}

	/**
     *
     * setFirstWeekDay() -> sets the first day of the week, currently only Sunday and Monday supported, $daynum=0 -> Sunday
     *
	*/
	public function setFirstWeekDay($daynum) {
		if ($daynum==0) {
			$this->startOnSun=true;
		}
		else {
			$this->startOnSun=false;
		}
	}

	/**
     *
     * showYear() -> returns the year's view as html table string
     * Each private method returns a tr tag of the table as a string.
     * You can change the calendar structure by simply calling these private methods in another order
     *
	*/
	public function showYear($rowCount=false,$startMonth=false) {
		if ($rowCount) {
			$this->rowCount=$rowCount;
		}
		$this->monthNav=false; // disables month navigation in yearview
		$out=$this->mkYearHead(); // this should remain first: opens table tag
		$out.=$this->mkYearTitle(); // tr tag: year title and navigation
		$out.=$this->mkDatePicker("yearonly"); // tr tag: year date picker (only year selection)
		$this->datePicker=false; // disables month date picker in yearview
		$out.=$this->mkYearBody($startMonth); // tr tag(s): year month (html tables)
		$out.=$this->mkYearFoot(); // this should remain last: closes table tag
		return $out;
	}

	/**
	********************************************************************************
	PUBLIC showMonth() -> returns the month's view as html table string
	Each private method returns a tr tag of the table as a string.
	You can change the calendar structure by simply calling these private methods in another order
	$showNoMonthDays = false: days, that do not belong to the current month, will not be displayed
	$showNoMonthDays = true: days, that do not belong to the current month, will be displayed
	(note: these 'noMonthDays' will not contain any events or eventcontents!)
	********************************************************************************
	*/
	function showMonth($showNoMonthDays=false) {
		$this->showNoMonthDays=$showNoMonthDays;
		$out=$this->mkMonthHead(); // this should remain first: opens table tag
		$out.=$this->mkMonthTitle(); // tr tag: month title and navigation
		$out.=$this->mkDatePicker(); // tr tag: month date picker (month and year selection)
		$out.=$this->mkWeekDays(); // tr tag: the weekday names
		if ($this->showNoMonthDays==false) {// tr tags: the days of the month
			$out.=$this->mkMonthBody();
		}
		else {// tr tags: the days of the month
			$out.=$this->mkMonthBody(1);
		}
		$out.=$this->mkMonthFoot(); // this should remain last: closes table tag
		return $out;
	}

	/**
	----------------------
	@START PRIVATE METHODS
	----------------------
	*/

	/**
	********************************************************************************
	THE FOLLOWING METHODS AND VARIABLES ARE PRIVATE. PLEASE DO NOT CALL OR MODIFY THEM
	********************************************************************************
	*/
	private $version="1.2.0";
	private $releaseDate="23 Feb 2006";
	private $monthSpan=7;
	private $timezone=false;
	private $yearNav=false;
	private $monthNav=false;
	private $dayLinks=false;
	private $dayNewEventLinks=false;
	private $datePicker=false;
	private $url=false;
	private $urlNav=false;
	private $urlPicker=false;
	private $htmlNewEventLink=false;
	private $calEvents=false;
	private $calEventsUrl=false;
	private $eventUrl=false;
	private $javaScriptDay=false;
	private $monthNames=false;
	private $dayNames=false;
	private $calEventContent=false;
	private $calEventContentUrl=false;
	private $calEventContentId=false;
	private $calInit=0;
	private $weekNum=false;
	private $WeekUrl=false;
	private $javaScriptWeek=false;

	/**
	********************************************************************************
	PRIVATE mkYearHead() -> creates the year table tag
	********************************************************************************
	*/
	private function mkYearHead() {
		return '<table class=\"' . $this->cssYearTable . '\">\n';
	}

	/**
	********************************************************************************
	PRIVATE mkYearTitle() -> creates the tile and navigation tr tag of the year table
	********************************************************************************
	*/
	private function mkYearTitle() {
		if ($this->rowCount<1 || $this->rowCount>12) {
			$this->rowCount=4;
		}
		if (!$this->yearNav) {
			$out="<tr><td colspan=\"".$this->rowCount."\" class=\"".$this->cssYearTitle."\">";
			$out.=$this->actyear;
			$out.="</td></tr>\n";
		} else {
			$out="<tr><td colspan=\"".$this->rowCount."\" align=\"center\">";
			$out.="<table><tr><td class=\"".$this->cssYearNav."\">";
			$out.=$this->mkUrl($this->actyear-1);
			$out.=$this->yearNavBack."</a></td>";
			$out.="<td class=\"".$this->cssYearTitle."\">".$this->actyear."</td>";
			$out.="<td class=\"".$this->cssYearNav."\">";
			$out.=$this->mkUrl($this->actyear+1);
			$out.=$this->yearNavForw."</a></td></tr></table></td></tr>\n";
		}
		return $out;
	}

	/**
	********************************************************************************
	PRIVATE mkYearBody() -> creates the tr tags of the year table
	********************************************************************************
	*/
	private function mkYearBody($stmonth=false) {
		if (!$stmonth || $stmonth>12) {
			$stmonth=1;
		}
		$TrMaker = $this->rowCount;
		$curyear = $this->actyear;
		$out="<tr>\n";
		for ($x=1; $x<=12; $x++) {
			$this->activeCalendar($curyear,$stmonth,false,$this->GMTDiff);
			$out.="<td valign=\"top\">\n".$this->showMonth()."</td>\n";
			if ($x == $TrMaker && $x < 12) {
				$out.="</tr><tr>";
				$TrMaker = ($TrMaker+$this->rowCount);
			}
			if ($stmonth == 12) {
				$stmonth = 1;
				$curyear++;
			}
			else $stmonth++;
		}
		$out.="</tr>\n";
		return $out;
	}

	/**
	********************************************************************************
	PRIVATE mkYearFoot() -> closes the year table tag
	********************************************************************************
	*/
	private function mkYearFoot() {
		return "</table>\n";
	}

	/**
	********************************************************************************
	PRIVATE mkMonthHead() -> creates the month table tag
	********************************************************************************
	*/
	private function mkMonthHead() {
		return "<table class=\"".$this->cssMonthTable."\">\n";
	}

	/**
	********************************************************************************
	PRIVATE mkMonthTitle() -> creates the tile and navigation tr tag of the month table
	********************************************************************************
	*/
	private function mkMonthTitle() {
		if (!$this->monthNav) {
			$out="<tr><td class=\"".$this->cssMonthTitle."\" colspan=\"".$this->monthSpan."\">";
			$out.=$this->getMonthName().$this->monthYearDivider.$this->actyear;
			$out.="</td></tr>\n";
		} else {
			$out = "<tr><td class=\"".$this->cssMonthNav."\" colspan=\"2\" style=\"text-align:right;\">";
			if ($this->actmonth==1) { // january
				$out.=$this->mkUrl($this->actyear-1,"12");
			} else {
				$out.=$this->mkUrl($this->actyear,$this->actmonth-1);
			}
			$out .= $this->monthNavBack."</a></td>";
			$out .= "<td class=\"".$this->cssMonthTitle."\" colspan=\"".($this->monthSpan-4)."\">";
			$out .= $this->getMonthName().$this->monthYearDivider.$this->actyear."</td>";
			$out .= "<td class=\"".$this->cssMonthNav."\" colspan=\"2\" style=\"text-align:left;\">";
			if ($this->actmonth==12) { //december
				$out.=$this->mkUrl($this->actyear+1,"1");
			}
			else {
				$out.=$this->mkUrl($this->actyear,$this->actmonth+1);
			}
			$out.=$this->monthNavForw."</a></td></tr>\n";
		}
		return $out;
	}

	/**
	********************************************************************************
	PRIVATE mkDatePicker() -> creates the tr tag for the date picker
	********************************************************************************
	*/
	private function mkDatePicker($yearpicker=false) {
		if ($this->datePicker && !empty($this->urlPicker)) {
			if ($yearpicker) $pickerSpan=$this->rowCount;
			else $pickerSpan=$this->monthSpan;
			$out="<tr><td class=\"".$this->cssPicker."\" colspan=\"".$pickerSpan."\">\n";
			$out.="<form name=\"".$this->cssPickerForm."\" class=\"".$this->cssPickerForm."\" action=\"".$this->urlPicker."\" method=\"get\">\n";
			if (!$yearpicker) {
				$out.="<select name=\"".$this->monthID."\" class=\"".$this->cssPickerMonth."\">\n";
				for ($z=1;$z<=12;$z++) {
					if ($z==$this->actmonth) $out.="<option value=\"".$z."\" selected=\"selected\">".$this->getMonthName($z)."</option>\n";
					else $out.="<option value=\"".$z."\">".$this->getMonthName($z)."</option>\n";
				}
				$out.="</select>\n";
			}
			$out.="<select name=\"".$this->yearID."\" class=\"".$this->cssPickerYear."\">\n";
			for ($z=$this->startYear;$z<=$this->endYear;$z++) {
				if ($z==$this->actyear) $out.="<option value=\"".$z."\" selected=\"selected\">".$z."</option>\n";
				else $out.="<option value=\"".$z."\">".$z."</option>\n";
			}
			$out.="</select>\n";
			$out.="<input type=\"submit\" value=\"".$this->selBtn."\" class=\"".$this->cssPickerButton."\"></input>\n";
			$out.="</form>\n";
			$out.="</td></tr>\n";
		}
		else $out="";
		return $out;
	}

	/**
	********************************************************************************
	PRIVATE mkWeekDays() -> creates the tr tag of the month table for the weekdays
	********************************************************************************
	*/
	private function mkWeekDays() {
		if ($this->startOnSun) {
			$out='<tr class="daynamesRow">';
			if ($this->weekNum) $out.="<td class=\"".$this->cssWeekNumTitle."\">".$this->weekNumTitle."</td>";
			for ($x=0;$x<=6;$x++) $out.="<td class=\"".$this->cssWeekDay."\">".$this->getDayName($x)."</td>";
			$out.="</tr>\n";
		} else {
			$out='<tr class="daynamesRow">';
			if ($this->weekNum) $out.="<td class=\"".$this->cssWeekNumTitle."\">".$this->weekNumTitle."</td>";
			for ($x=1;$x<=6;$x++) $out.="<td class=\"".$this->cssWeekDay."\">".$this->getDayName($x)."</td>";
			$out.="<td class=\"".$this->cssWeekDay."\">".$this->getDayName(0)."</td>";
			$out.="</tr>\n";
			$this->firstday=$this->firstday-1;
			if ($this->firstday<0) $this->firstday=6;
		}
		return $out;
	}

	/**
	********************************************************************************
	PRIVATE mkMonthBody() -> creates the tr tags of the month table
	********************************************************************************
	*/
	private function mkMonthBody($showNoMonthDays=0) {
		if ($this->actmonth==1) {
			$pMonth=12;
			$pYear=$this->actyear-1;
		} else{
			$pMonth=$this->actmonth-1;
			$pYear=$this->actyear;
		}
		$out='<tr class="daysRow">';
		$cor=0;
		if ($this->startOnSun) $cor=1;
		if ($this->weekNum) $out.="<td class=\"".$this->cssWeekNum."\">".$this->mkWeekNum(1+$cor)."</td>";
		$monthday=0;
		$nmonthday=1;
		for ($x=0; $x<=6; $x++) {
			if ($x>=$this->firstday) {
				$monthday++;
				$out.=$this->mkDay($monthday);
			} else {
				if ($showNoMonthDays==0) $out.="<td class=\"".$this->cssNoMonthDay."\"></td>";
				else $out.="<td class=\"".$this->cssNoMonthDay."\">".($this->getMonthDays($pMonth,$pYear)-($this->firstday-1)+$x)."</td>";
			}
		}
		$out.="</tr>\n";
		$goon=$monthday+1;
		$stop=0;
		for ($x=0; $x<=6; $x++) {
			if ($goon>$this->maxdays) break;
			if ($stop==1) break;
			$out.='<tr class="daysRow">';
			if ($this->weekNum) $out.="<td class=\"".$this->cssWeekNum."\">".$this->mkWeekNum($goon+$cor)."</td>";
				for ($i=$goon; $i<=$goon+6; $i++) {
					if ($i>$this->maxdays) {
						if ($showNoMonthDays==0) $out.="<td class=\"".$this->cssNoMonthDay."\"></td>";
						else $out.='<td class="'.$this->cssNoMonthDay.'"><div class="daynum">'.$nmonthday++.'</div></td>';
						$stop=1;
					}
					else $out.=$this->mkDay($i);
				}
			$goon=$goon+7;
			$out.="</tr>\n";
		}
		$this->selectedday="-2";
		return $out;
	}

	/**
	********************************************************************************
	PRIVATE mkDay() -> creates each td tag of the month body
	********************************************************************************
	*/
	private function mkDay($var) {
		$eventContent = $this->mkEventContent($var);

		$linktext = $var;
		if ($this->dayLinks) {
			if ($eventContent) {
				if ($this->javaScriptDay) {
					$linktext="<a href=\"javascript:".$this->javaScriptDay."(".$this->actyear.",".$this->actmonth.",".$var.")\">".$var."</a>";
				} elseif (!empty($this->url)) {
					$dayurl= JRoute::_($this->url.(strpos($this->url,"?") === false ? '?' : '&').'id='.sprintf('%04d%02d%02d',$this->actyear,$this->actmonth,$var));
					$linktext="<a href=\"".$dayurl."\">".$var."</a>";
				}
			}
		}

		$htmlNewEventLink = '';
		if ($this->dayNewEventLinks) {
			$htmlNewEventLink = preg_replace('/{date}/', sprintf('%04d-%02d-%02d', $this->actyear, $this->actmonth, $var), $this->htmlNewEventLink);
		}

		if ($this->isEvent($var)) {
			if ($this->eventUrl) {
				$out="<td class=\"".$this->eventID."\"><div class=\"daynum\">".$htmlNewEventLink."<a href=\"".$this->eventUrl."\">".$var."</a></div>".$eventContent."</td>";
				$this->eventUrl=false;
			} else {
				$out="<td class=\"".$this->eventID."\"><div class=\"daynum\">".$htmlNewEventLink.$linktext.'</div>'.$eventContent."</td>";
			}
		} else {
			/* allow styling of multiple things like "today is Sunday" */
			$cssClass = array($this->cssMonthDay);
			if (($var == $this->selectedday) && ($this->actmonth == $this->selectedmonth) && ($this->actyear == $this->selectedyear)) {
				$cssClass[] = $this->cssSelecDay;
			}
			if (($var == $this->daytoday) && ($this->actmonth == $this->monthtoday) && ($this->actyear == $this->yeartoday)) {
				$cssClass[] = $this->cssToday;
			}
			if (($this->getWeekday($var) == 0) && $this->crSunClass) {
				$cssClass[] = $this->cssSunday;
			}
			if (($this->getWeekday($var) == 6) && $this->crSatClass) {
				$cssClass[] = $this->cssSaturday;
			}
			$out = "<td class=\"".implode(' ', $cssClass)."\"><div class=\"daynum\">".$htmlNewEventLink.$linktext.'</div>'.$eventContent."</td>";
		}

		return $out;
	}

	/**
	********************************************************************************
	PRIVATE mkMonthFoot() -> closes the month table
	********************************************************************************
	*/
	private function mkMonthFoot() {
		return "</table>\n";
	}

	/**
	********************************************************************************
	PRIVATE mkUrl() -> creates the day and navigation link structure
	********************************************************************************
	*/
	private function mkUrl($year, $month=false, $day=false) {
		if (strpos($this->url,"?") === false) {
			$glue="?";
		} else {
			$glue="&amp;";
		}
		if (strpos($this->urlNav,"?") === false) {
			$glueNav="?";
		} else {
			$glueNav="&amp;";
		}
		$yearNavLink  = empty($this->urlNav) ? '' : "<a href=\"".JRoute::_($this->urlNav.$glueNav.$this->yearID."=".$year)."\" rel=\"noindex, nofollow\">";
		$monthNavLink = empty($this->urlNav) ? '' : "<a href=\"".JRoute::_($this->urlNav.$glueNav.$this->yearID."=".$year."&amp;".$this->monthID."=".$month)."\" rel=\"noindex, nofollow\">";
		$dayLink      = empty($this->url)  ? $day : "<a href=\"".JRoute::_($this->url.$glue.$this->yearID."=".$year."&amp;".$this->monthID."=".$month."&amp;".$this->dayID."=".$day)."\">".$day."</a>";
		if ($year &&  $month &&  $day) return $dayLink;
		if ($year && !$month && !$day) return $yearNavLink;
		if ($year &&  $month && !$day) return $monthNavLink;
	}

	/**
	********************************************************************************
	PRIVATE mkEventContent() -> creates the table for the event content
	********************************************************************************
	*/
	private function mkEventContent_old($var)
	{
		$hasContent=$this->hasEventContent($var);
		$out="";
		if ($hasContent) {
			for ($x=0;$x<count($hasContent);$x++) {
				foreach($hasContent[$x] as $eventContentid => $eventContentData) {
					foreach($eventContentData as $eventContentUrl => $eventContent) {
						$out.="<table class=\"".$eventContentid."\">";
						if (is_string($eventContent)) {
							if (is_int($eventContentUrl)) {
								$out.="<tr><td>".$eventContent."</td></tr></table>";
							} else {
								$out.="<tr><td><a href=\"".$eventContentUrl."\">".$eventContent."</a></td></tr></table>";
							}
						} else if (is_array($eventContent)) {
							foreach($eventContent as $arrayContent) {
								if (is_int($eventContentUrl)) {
									$out.="<tr><td>".$arrayContent."</td></tr>";
								} else {
									$out.="<tr><td><a href=\"" . $eventContentUrl . "\">" . $arrayContent . "</a></td></tr>";
								}
							}
							$out.="</table>";
						}
						else $out="";
					}
				}
			}
		}
		return $out;
	}

	/**
	 * creates the div for the event content
	 *
	 * @param int $var the day
	 * @return string
	 */
	private function mkEventContent($var) {
		$hasContent=$this->hasEventContent($var);
		$out="";
		if ($hasContent) {
			for ($x=0;$x<count($hasContent);$x++) {
				foreach ($hasContent[$x] as $eventContentid => $eventContentData) {
					foreach ($eventContentData as $eventContentUrl => $eventContent) {
						if (is_string($eventContent)) {
							$eventContent = array($eventContent);
						}

						foreach($eventContent as $arrayContent) {
							$out .= '<div class="'.$eventContentid.'">';
							if (is_int($eventContentUrl)) {
								$out.= $arrayContent;
							} else {
								$out.='<a href="'.$eventContentUrl.'">'.$arrayContent.'</a>';
							}
							$out.='</div>';
						}
					}
				}
			}
		}
		return $out;
	}

	/**
	********************************************************************************
	PRIVATE mkWeekNum() -> returns the week number and optionally creates a link
	********************************************************************************
	*/
	private function mkWeekNum($var) {
		$year=$this->actyear;
		$week=$this->getWeekNum($var);
		if ($week>50 && $this->actmonth==1) $year=$this->actyear-1;
		$out="";
		if ($this->weekUrl) {
			if (strpos($this->weekUrl,"?") === false) $glue="?";
			else $glue="&amp;";
			$out.="<a href=\"".$this->weekUrl.$glue.$this->yearID."=".$year."&amp;".$this->weekID."=".$week."\">".$week."</a>";
		}
		elseif ($this->javaScriptWeek) $out.="<a href=\"javascript:".$this->javaScriptWeek."(".$year.",".$week.")\">".$week."</a>";
		else $out.=$week;
		return $out;
	}

	/**
	********************************************************************************
	PRIVATE getMonthName() -> returns the month's name, according to the configuration
	********************************************************************************
	*/
	private function getMonthName($var=false) {
		if (!$var) $var=@$this->actmonth;
		if ($this->monthNames) return $this->monthNames[$var-1];
		switch($var) {
			case 1: return JText::_($this->jan);
			case 2: return JText::_($this->feb);
			case 3: return JText::_($this->mar);
			case 4: return JText::_($this->apr);
			case 5: return JText::_($this->may);
			case 6: return JText::_($this->jun);
			case 7: return JText::_($this->jul);
			case 8: return JText::_($this->aug);
			case 9: return JText::_($this->sep);
			case 10: return JText::_($this->oct);
			case 11: return JText::_($this->nov);
			case 12: return JText::_($this->dec);
		}
	}

	/**
	********************************************************************************
	PRIVATE getDayName() -> returns the day's name, according to the configuration
	********************************************************************************
	*/
	private function getDayName($var=false) {
		if ($this->dayNames) return $this->dayNames[$var];
		switch($var) {
			case 0: return JText::_($this->sun);
			case 1: return JText::_($this->mon);
			case 2: return JText::_($this->tue);
			case 3: return JText::_($this->wed);
			case 4: return JText::_($this->thu);
			case 5: return JText::_($this->fri);
			case 6: return JText::_($this->sat);
		}
	}

	/**
	********************************************************************************
	PRIVATE getMonthDays() -> returns the number of days of the month specified
	********************************************************************************
	*/
	private function getMonthDays($month,$year) {
		$has31days=checkdate($month,31,$year);
		$isSchalt=checkdate(2,29,$year);
		if ($isSchalt==1 && $month==2) $maxdays=29;
		elseif ($isSchalt!=1 && $month==2) $maxdays=28;
		elseif ($has31days==1) $maxdays=31;
		else $maxdays=30;
		return $maxdays;
	}

	/**
	********************************************************************************
	PRIVATE getWeekday() -> returns the weekday's number, 0 = Sunday ... 6 = Saturday
	********************************************************************************
	*/
	private function getWeekday($var) {
		return $this->mkActiveDate("w", $this->mkActiveTime(0,0,1,$this->actmonth,$var,$this->actyear));
	}

	/**
	********************************************************************************
	PRIVATE getWeekNum() -> returns the week number, php version > 4.1.0, unsupported by the ADOdb Date Library
	********************************************************************************
	*/
	private function getWeekNum($var) {
		return date("W", $this->mkActiveTime(0,0,1,$this->actmonth,$var,$this->actyear))+0;
	}

	/**
	********************************************************************************
	PRIVATE isEvent() -> checks if a date was set as an event and creates the eventID (css layout) and eventUrl
	********************************************************************************
	*/
	private function isEvent($var) {
		if ($this->calEvents) {
			$checkTime=$this->mkActiveTime(0,0,1,$this->actmonth,$var,$this->actyear);
			$selectedTime=$this->mkActiveTime(0,0,1,$this->selectedmonth,$this->selectedday,$this->selectedyear);
			$todayTime=$this->mkActiveTime(0,0,1,$this->monthtoday,$this->daytoday,$this->yeartoday);
			foreach($this->calEvents as $eventTime => $eventID) {
				if ($eventTime==$checkTime) {
					if ($eventTime==$selectedTime) $this->eventID=$this->cssPrefixSelecEvent.$eventID;
					elseif ($eventTime==$todayTime) $this->eventID=$this->cssPrefixTodayEvent.$eventID;
					else $this->eventID=$eventID;
					if ($this->calEventsUrl[$eventTime]) $this->eventUrl=$this->calEventsUrl[$eventTime];
					return true;
				}
			}
			return false;
		}
	}

	/**
	********************************************************************************
	PRIVATE hasEventContent() -> checks if an event content was set
	********************************************************************************
	*/

	/**
	 * checks if an event content was set for the day
	 *
	 * @param int $var day of the month
	 * @return array
	 */
	private function hasEventContent($var) {
		$hasContent = false;
		if ($this->calEventContent) {
			$checkTime = $this->mkActiveTime(0, 0, 1, $this->actmonth, $var, $this->actyear);
			for ($x=0; $x < count($this->calEventContent); $x++) {
				$eventContent = $this->calEventContent[$x];
				$eventContentUrl = $this->calEventContentUrl[$x];
				$eventContentId = $this->calEventContentId[$x];
				foreach ($eventContent as $eventTime => $eventContent) {
					if ($eventTime == $checkTime) $hasContent[][$eventContentId][$eventContentUrl] = $eventContent;
				}
			}
		}
		return $hasContent;
	}

	/**
	********************************************************************************
	PRIVATE mkActiveDate() -> checks if ADOdb Date Library is loaded and calls the date function
	********************************************************************************
	*/
	private function mkActiveDate($param,$acttime=false) {
		if (!$acttime) $acttime=$this->timetoday;
		if (function_exists("adodb_date")) return adodb_date($param,$acttime);
		else return date($param,$acttime);
	}

	/**
	********************************************************************************
	PRIVATE mkActiveGMDate() -> checks if ADOdb Date Library is loaded and calls the gmdate function
	********************************************************************************
	*/
	private function mkActiveGMDate($param,$acttime=false) {
		if (!$acttime) $acttime=time();
		if (function_exists("adodb_gmdate")) return adodb_gmdate($param,$acttime);
		else return gmdate($param,$acttime);
	}

	/**
	********************************************************************************
	PRIVATE mkActiveTime() -> checks if ADOdb Date Library is loaded and calls the mktime function
	********************************************************************************
	*/
	/**
	 * checks if ADOdb Date Library is loaded and calls the mktime function
	 *
	 * @param int $hr
	 * @param int $min
	 * @param int $sec
	 * @param int $month [optional]
	 * @param int $day [optional]
	 * @param int $year [optional]
	 * @return int
	 */
	private function mkActiveTime($hr,$min,$sec,$month=false,$day=false,$year=false) {
		if (function_exists("adodb_mktime")) {
		    return adodb_mktime($hr,$min,$sec,$month,$day,$year);
        }
		else {
		    return mktime($hr,$min,$sec,$month,$day,$year);
        }
	}
}
