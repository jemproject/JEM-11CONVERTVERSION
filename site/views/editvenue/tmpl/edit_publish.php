<?php
/**
 * @version 2.3.1
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

//$max_custom_fields = $this->settings->get('global_editvenue_maxnumcustomfields', -1); // default to All
?>

			<fieldset>
				<legend><?php echo JText::_('COM_JEM_EDITVENUE_PUBLISHING_LEGEND'); ?></legend>
				<ul class="adminformlist">					
					<li><?php echo $this->form->getLabel('published'); ?><?php echo $this->form->getInput('published'); ?></li>
				</ul>
			</fieldset>
				<!-- META -->
			<fieldset class="">
				<legend><?php echo JText::_('COM_JEM_META_HANDLING'); ?></legend>
				<input type="button" class="button" value="<?php echo JText::_('COM_JEM_ADD_VENUE_CITY'); ?>" onclick="meta()" />
				<?php foreach ($this->form->getFieldset('meta') as $field) : ?>
					<div class="control-group">
						<div class="control-label"><?php echo $field->label; ?></div>
						<div class="controls"><?php echo $field->input; ?></div>
					</div>
				<?php endforeach; ?>
			</fieldset>

