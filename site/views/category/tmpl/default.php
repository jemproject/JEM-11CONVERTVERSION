<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

JHtml::_('behavior.modal');
?>
<div id="jem" class="jem_category<?php echo $this->pageclass_sfx;?>">
	<div class="buttons">
		<?php
		echo JemOutput::submitbutton($this->dellink, $this->params);
		echo JemOutput::archivebutton($this->params, $this->task, $this->category->slug);
		echo JemOutput::mailbutton($this->category->slug, 'category', $this->params);
		echo JemOutput::printbutton($this->print_link, $this->params);
		?>
	</div>

	<?php if ($this->params->get('show_page_heading', 1)) : ?>
	<h1 class='componentheading'>
		<?php echo $this->escape($this->params->get('page_heading')); ?>
	</h1>
	<?php endif; ?>

	<div class="clr"></div>

	<div class="floattext">
		<?php if ($this->jemsettings->discatheader) : ?>
		<div class="catimg">
		<?php
		// flyer
		if (empty($this->category->image)) {
			$jemsettings = JEMHelper::config();
			$imgattribs['width'] = $jemsettings->imagewidth;
			$imgattribs['height'] = $jemsettings->imagehight;

			echo JHtml::_('image', 'com_jem/noimage.png', $this->category->catname, $imgattribs, true);
		}
		else {
			echo JemOutput::flyer($this->category, $this->cimage, 'category');
		}
		?>
		</div>
		<?php endif; ?>

		<div class="description">
			<p><?php echo $this->description; ?></p>
		</div>
	</div>

	<!--subcategories-->
	<?php
	if (!empty($this->children[$this->category->id])&& $this->maxLevel != 0) {
?>
		<div class="cat-children">
		<?php if ($this->params->get('show_category_heading_title_text', 1) == 1) : ?>
		<h3>
			<?php echo JTEXT::_('JGLOBAL_SUBCATEGORIES'); ?>
		</h3>
		<?php endif; ?>
		<?php echo $this->loadTemplate('subcategories'); ?>
		</div>
		<?php };?>


	<form action="<?php echo $this->action; ?>" method="post" id="adminForm">
	<!--table-->
		<?php echo $this->loadTemplate('events_table'); ?>
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
		<input type="hidden" name="view" value="category" />
		<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
		<input type="hidden" name="id" value="<?php echo $this->category->id; ?>" />
	</form>

	<!--pagination-->
	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>

	<!-- iCal -->
	<div id="iCal" class="iCal">
		<?php echo JemOutput::icalbutton($this->category->id, 'category'); ?>
	</div>

	<!-- copyright -->
	<div class="copyright">
		<?php echo JemOutput::footer( ); ?>
	</div>
</div>