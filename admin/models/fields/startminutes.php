<?php
/**
 * @version 2.3.1
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('JPATH_BASE') or die;

//JFormHelper::loadFieldClass('list');

jimport('joomla.html.html');
jimport('joomla.form.formfield');



/**
 * CountryOptions Field class.
 *
 * 
 */
class JFormFieldStartminutes extends JFormField
{
	/**
	 * The form field type.
	 *
	 */
	protected $type = 'Startminutes';


	
	
	public function getInput()
	{

	
		$startminutes = JEMAdmin::buildtimeselect(59, 'startminutes', substr( $this->name, 3, 2 ));
	
		return $startminutes;
		
	}
	
}
