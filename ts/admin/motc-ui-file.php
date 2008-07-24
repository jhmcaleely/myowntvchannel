<?php

/*
 * Copyright John McAleely, 2008
 */


function ui_writeFileItem($op, $item, $cf_channel) {

	$op->startElement('tr');

	$op->startElement('td');
	ui_writeInputCheck($op, ui_name_with_ID('include', $item->id), $item->status == 'included');
	$op->endElement();

	$op->startElement('td');
	ui_writeInputHidden($op, ui_name_with_ID('index', $item->id), (string) $item->id);
	$op->text($item->filename);
	$op->endElement();

	$op->startElement('td');
	ui_writeInputText($op, ui_name_with_ID('title', $item->id), 20, (string) $item->title);
	$op->endElement();

	$op->startElement('td');
	ui_writeInputText($op, ui_name_with_ID('description', $item->id), 40, (string) $item->description);
	$op->endElement();

	$op->startElement('td');
	ui_writeLogoIconSelect($op, ui_name_with_ID('icon', $item->id), $cf_channel->icons->icon, (string) $item->icon, 'Channel Item Logo', 'motc-item');
	$op->endElement();

	$op->endElement();
}


function ui_writeEditFileDescription($op, $config) {

	$op->writeElement('h2', 'Edit Files');
	
	ui_startForm($op, '');
	ui_writeInputHidden($op, 'page', 'edit');
	ui_writeInputSubmit($op, 'Save', 'save-items');
	ui_writeInputSubmit($op, 'Cancel', 'display');
	$op->writeElement('br');

	if (isset($config->items->item)) {
		ui_startTable($op, array('Include', 'Filename', 'Title', 'Description', 'Icon'));

		foreach ($config->items->item as $i) {
			ui_writeFileItem($op, $i, $config);
		}

		$op->endElement();
	}
	else {
		$op->text('No channel items in directory');
	}

	$op->endElement();
}


function ui_writeEditFileOrder($op, $config) {

	$op->writeElement('h2', 'Edit File Order');
	
	ui_startForm($op, '');
	ui_writeInputHidden($op, 'page', 'order-items');
	ui_writeInputSubmit($op, 'Edit...', 'edit-items');
	ui_writeInputSubmit($op, 'Done', 'display');
	
	ui_startTable($op, array('Filename', 'Title', 'Description', 'Status', 'Position'));

	if (isset($config->items->item)) {
		foreach ($config->items->item as $i) {
			$op->startElement('tr');

			$op->writeElement('td', $i->filename);
			$op->writeElement('td', $i->title);
			$op->writeElement('td', $i->description);
			$op->writeElement('td', $i->status);

			$op->startElement('td');
			ui_writeInputSubmit($op, 'up', ui_name_with_ID('up', $i->id));
			ui_writeInputSubmit($op, 'down', ui_name_with_ID('down', $i->id));
			$op->endElement();

			$op->endElement();
		}
	}

	$op->endElement();

	$op->endElement();
	
}


function ui_writeFiles($op, $config) {
	
	$op->writeElement('h2', 'Files');
	ui_startForm($op, '');
	ui_writeInputHidden($op, 'page', 'display');
	ui_writeInputSubmit($op, 'Edit...', 'edit-items');
	ui_writeInputSubmit($op, 'Change Order...', 'order-items');
	ui_writeInputSubmit($op, 'Rebuild Torrents', 'rebuild');

	ui_startTable($op, array('Filename', 'Title', 'Description', 'Status'));

	for ($x = 0; $x < count($config->items->item); $x++) {

		$i = $config->items->item[$x];
		$status_id = 'status'.$x;

		$op->startElement('tr');

		$op->writeElement('td', $i->filename);
		$op->writeElement('td', $i->title);
		$op->writeElement('td', $i->description);
		$op->startElement('td');
		$op->startElement('span');
		$op->writeAttribute('id', "$status_id");
		$op->text($i->status);
		$op->endElement();
		$op->endElement();

		$op->endElement();
	}

	$op->endElement();

	$op->endElement();
	
}


function get_property_from_post($property, $i) {
	return isset($_POST[$property.'_'.$i->id]) ? $_POST[$property.'_'.$i->id] : $i->$property;
}


function ui_update_channel_items($config) {

	if (isset($config->items->item)) {
		foreach ($config->items->item as $i) {
			
			$props = array('title', 'description', 'icon');
			
			foreach ($props as $p) {
				$new->$p = get_property_from_post($p, $i);
			}

			if (isset($_POST['index_'.$i->id])) {
				$new_status = isset($_POST['include_'.$i->id]) ? 'included' : 'ignored';
			}
			else {
				$new_status = $i->status;
			}

			// to be valid rss, each item must have a title or description.
			if (    ($new_status == 'included' && (strlen($new->title) > 0 || strlen($new->description) > 0))
			    || ($new_status == 'ignored')) {
				$i->status = $new_status;
				
				foreach ($props as $p) {
					$i->$p = $new->$p;
				}
			}
			else {
				$display_message = TRUE;
			}
		}
	}

	if (isset($display_message) && $display_message) {
		return 'included files must have a title or description.';
	}

	return TRUE;
}


function ui_update_channel_order($config) {

	if (isset($config->items->item)) {
		$cursor = 0;
		foreach ($config->items->item as $i) {
			if (isset($_POST['up_'.$i->id])) {
				$x = $cursor - 1;
				$y = $cursor;
				$swap = TRUE;
			}
			else if (isset($_POST['down_'.$i->id])) {
				$x = $cursor;
				$y = $cursor + 1;
				$swap = TRUE;
			}
			$cursor++;
		}
	}
		
	if ($swap && $x >= 0 && $y < $cursor) {
		swap_items($config, $x, $y);
	}
}


function swap_items($config, $x, $y) {
	
	$intproperties = array('id');
	$stringproperties = array('filename', 'title', 'torrent_file', 'status', 'description', 'icon');
	foreach ($intproperties as $prop) {
		$cache->$prop = (int) $config->items->item[$x]->$prop;
		$config->items->item[$x]->$prop = (int)$config->items->item[$y]->$prop;
		$config->items->item[$y]->$prop = (int)$cache->$prop;		
	}

	foreach ($stringproperties as $prop) {
		$cache->$prop = (string) $config->items->item[$x]->$prop;
		$config->items->item[$x]->$prop = (string)$config->items->item[$y]->$prop;
		$config->items->item[$y]->$prop = (string)$cache->$prop;		
	}
}

?>