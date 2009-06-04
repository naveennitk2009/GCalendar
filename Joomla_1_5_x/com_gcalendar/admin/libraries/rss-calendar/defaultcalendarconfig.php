<?php
/**
 * GCalendar is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * GCalendar is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GCalendar.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Allon Moritz
 * @copyright 2007-2009 Allon Moritz
 * @version $Revision: 2.1.0 $
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_gcalendar'.DS.'util.php');
require_once ('eventrenderer.php');
require_once ('calendarrenderer.php');

class DefaultCalendarConfig{

	var $feedFetcher;
	var $defaultView = 'month';
	var $forceView = null;
	var $weekStart = '1';
	var $dateFormat = 'dd/mm/yy';
	var $showEventTitle = true;
	var $shortDayNames = false;
	var $cellHeight = 90;
	var $printDayLink = true;

	var $cal;
	var $month, $year, $day;
	var $view;
	var $feeds;

	function DefaultCalendarConfig($feedFetcher){
		$this->feedFetcher = $feedFetcher;
	}

	function print(){
		$today = getdate();
		$this->month = JRequest::getVar('month', $today["mon"]);
		$this->year = JRequest::getVar('year', $today["year"]);
		$this->day = JRequest::getVar('day', $today["mday"]);
		if (!checkdate($this->month, $this->day, $this->year)) {
			$this->day = 1;
		}

		$this->view = JRequest::getVar('gcalendarview', $this->getDefaultView());
		if($this->getForceView() != null){
			$this->view = $this->getForceView();
		}

		$this->year = (int)$this->year;
		$this->month = (int)$this->month;
		$this->day = (int)$this->day;

		$userAgent = "unk";
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			$uaRaw = strtolower($_SERVER['HTTP_USER_AGENT']);
			if (strpos($uaRaw, "opera") !== false)
			$userAgent = "opera";
			elseif (strpos($uaRaw, "msie") !== false) {
				$userAgent = "ie";
			}
			else
			$userAgent = "other";
		}

		switch($this->view) {
			case "month":
				$start = mktime(0, 0, 0, $this->month, 1, $this->year);
				$end = strtotime( "+1 month", $start );
				break;
			case "day":
				$start = mktime(0, 0, 0, $this->month, $this->day, $this->year);
				$end = strtotime( "+1 day", $start );
				break;
			case "week":
				$start = $this->getFirstDayOfWeek($this->year, $this->month, $this->day, $this->getWeekStart());
				$end = strtotime( "+1 week +1 day", $start );
		}
		$this->feeds = $this->getGoogleCalendarFeeds($start, $end);
		$this->cal = new CalendarRenderer($this);

		$document =& JFactory::getDocument();
		JHTML::_('behavior.modal');
		$document->addScript('administrator/components/com_gcalendar/libraries/nifty/nifty.js');
		$document->addStyleSheet('administrator/components/com_gcalendar/libraries/nifty/niftyCorners.css');
		$document->addStyleSheet('administrator/components/com_gcalendar/libraries/rss-calendar/gcalendar.css');
		if ($this->userAgent == "ie") {
			$document->addStyleSheet('administrator/components/com_gcalendar/libraries/rss-calendar/gcalendar-ie6.css');
		}

		if(!empty($feeds)){
			$document =& JFactory::getDocument();
			$calCode = "window.addEvent(\"domready\", function(){\n";
			foreach($feeds as $feed){
				$calCode .= "Nifty(\"div.gccal_".$feed->get('gcid')."\",\"small\");\n";
				$document->addStyleDeclaration("div.gccal_".$feed->get('gcid')."{padding: 1px;margin:0 auto;background:#".$feed->get('gccolor')."}");
				$document->addStyleDeclaration("div.gccal_".$feed->get('gcid')." a{color: #FFFFFF}");
			}
			$calCode .= "});";
			$document->addScriptDeclaration($calCode);
		}

		echo "<div class=\"gcalendar\">\n";
		$this->printToolBar();
		$cal->printCal();
		echo "</div>\n";
	}

	function getGoogleCalendarFeeds($start, $end){
		if($this->feeds == null){
			$feedFetcher = $this->feedFetcher;
			$this->feeds = $feedFetcher->getGoogleCalendarFeeds($start, $end);
		}
		return $this->feeds;
	}

	function getDefaultView(){
		return $this->defaultView;
	}

	function getForceView(){
		return $this->forceView;
	}

	function getWeekStart(){
		return $this->weekStart;
	}

	function printToolbar(){
		echo $this->getViewTitle();
	}

	function getDateFormat(){
		return $this->dateFormat;
	}

	function getShowEventTitle(){
		return $this->showEventTitle;
	}

	function getShortDayNames(){
		return $this->shortDayNames;
	}

	function getCellHeight() {
		return $this->cellHeight;
	}

	function getPrintDayLink() {
		return $this->printDayLink;
	}

	function createLink($year, $month, $day, $calids){
		$calids = $this->getIdString($calids);
		return JRoute::_("index.php?option=com_gcalendar&view=gcalendar&gcalendarview=day&year=".$year."&month=".$month."&day=".$day.$calids);
	}

	function getViewTitle() {
		$year = (int)$this->year;
		$month = (int)$this->month;
		$day = (int)$this->day;
		$date = JFactory::getDate();
		$title = '';
		switch($this->view) {
			case "month":
				$title = $date->_monthToString($month)." ".$year;
				break;
			case "week":
				$firstDisplayedDate = $this->getFirstDayOfWeek($year, $month, $day, $this->getWeekStart());
				$lastDisplayedDate = strtotime("+6 days", $firstDisplayedDate);
				$infoS = getdate($firstDisplayedDate);
				$infoF = getdate($lastDisplayedDate);

				if ($infoS["year"] != $infoF["year"]) {
					$m1 = substr($infoS["month"], 0, 3);
					$m2 = substr($infoF["month"], 0, 3);

					$title = $infoS["year"] . " ${m1} " . $infoS["mday"] . " - " . $infoF["year"] . " ${m2} " . $infoF["mday"];
	}else if ($infoS["mon"] != $infoF["mon"]) {
		$m1 = substr($infoS["month"], 0, 3);
		$m2 = substr($infoF["month"], 0, 3);

		$title = $infoS["year"] . " ${m1} " . $infoS["mday"] . " - ${m2} " . $infoF["mday"];
				} else {
					$title = $infoS["year"] . " " . $infoS["month"] . " ". $infoS["mday"] . " - " . $infoF["mday"];
				}
				break;
			case "day":
				$tDate = strtotime("${year}-${month}-${day}");
				$title = strftime("%A, %Y %b %e", $tDate);
				break;
		}
		return $title;
	}

	function getFirstDayOfWeek($year, $month, $day, $weekStart) {
		$tDate = strtotime($year.'-'.$month.'-'.$day);

		switch($weekStart){
			case 1:
				$name = 'Sunday';
				break;
			case 2:
				$name = 'Monday';
				break;
			case 7:
				$name = 'Saturday';
				break;
			default:
				$name = 'Sunday';
		}
		if (strftime("%w", $tDate) == $weekStart-1) {
			return $tDate;
		}else {
			return strtotime("last ".$name, $tDate);
		}
	}

	/**
	 * This is an internal helper method.
	 *
	 */
	function getIdString($calids){
		$calendars = '';
		$itemid = null;
		if(!empty($calids)){
			$calendars = '&gcids='.implode(',',$calids);
			$itemid = GCalendarUtil::getItemId($calids[0]);
			foreach ($calids as $cal) {
				$id = GCalendarUtil::getItemId($cal);
				if($id != $itemid){
					$itemid = null;
					break;
				}
			}
		}
		if($itemid !=null){
			return $calendars.'&Itemid='.$itemid;
		}
		return $calendars;
	}
}
?>